<?php

declare(strict_types=1);

namespace Tests\Feature\Domains\Identity\UserManagement\Services;

use App\Domains\Identity\Enums\UserStatus;
use App\Domains\Identity\UserManagement\Exceptions\UserInvitationDeliveryException;
use App\Domains\Identity\UserManagement\Models\UserInvitation;
use App\Domains\Identity\UserManagement\Services\UserInvitationService;
use App\Domains\Identity\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;
use Throwable;

final class UserInvitationServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_failed_delivery_keeps_user_pending_and_revokes_invitation(): void
    {
        $user = User::factory()->create([
            'status' => UserStatus::Pending,
            'email' => 'invitation-failure@example.test',
        ]);

        /*
        |--------------------------------------------------------------------------
        | Simulate a mail transport failure
        |--------------------------------------------------------------------------
        */

        Notification::extend('mail', function () {
            return new class {
                public function send(
                    object $notifiable,
                    object $notification,
                ): void {
                    throw new \RuntimeException(
                        'Mail transport unavailable.',
                    );
                }
            };
        });

        /*
        |--------------------------------------------------------------------------
        | Execute invitation service
        |--------------------------------------------------------------------------
        */

        try {
            app(UserInvitationService::class)
                ->sendInvitation($user);

            $this->fail(
                'The service should throw UserInvitationDeliveryException.',
            );
        } catch (UserInvitationDeliveryException $exception) {
            $this->assertSame(
                'The user was created, but the invitation email could not be delivered.',
                $exception->getMessage(),
            );

            $this->assertInstanceOf(
                \RuntimeException::class,
                $exception->getPrevious(),
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Verify user remains pending
        |--------------------------------------------------------------------------
        */

        $this->assertDatabaseHas('users', [
            'id' => $user->getKey(),
            'status' => UserStatus::Pending->value,
        ]);

        /*
        |--------------------------------------------------------------------------
        | Verify invitation was created and revoked
        |--------------------------------------------------------------------------
        */

        $invitation = UserInvitation::query()
            ->where('user_id', $user->getKey())
            ->firstOrFail();

        $this->assertNotNull($invitation->expires_at);
        $this->assertNotNull($invitation->created_at);
        $this->assertNotNull($invitation->revoked_at);
        $this->assertNull($invitation->used_at);

        /*
        |--------------------------------------------------------------------------
        | Verify audit events
        |--------------------------------------------------------------------------
        */

        $this->assertDatabaseHas('audit_events', [
            'event_key' => 'identity.user.invitation_created',
            'subject_id' => $user->getKey(),
        ]);

        $this->assertDatabaseMissing('audit_events', [
            'event_key' => 'identity.user.invitation_sent',
            'subject_id' => $user->getKey(),
        ]);
    }
}
