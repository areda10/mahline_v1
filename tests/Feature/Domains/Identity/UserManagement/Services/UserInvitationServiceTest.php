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

    public function test_sending_new_invitation_revokes_previous_active_invitation(): void
    {
        Notification::fake();

        $user = User::factory()->create([
            'status' => UserStatus::Pending,
            'email_verified_at' => null,
        ]);

        $previousInvitation = UserInvitation::query()->create([
            'id' => (string) \Illuminate\Support\Str::ulid(),
            'user_id' => $user->getKey(),
            'token_hash' => hash('sha256', 'previous-token'),
            'expires_at' => now()->addMinutes(30),
            'created_at' => now(),
        ]);

        app(UserInvitationService::class)->sendInvitation($user);

        $previousInvitation->refresh();

        $this->assertNotNull($previousInvitation->revoked_at);
        $this->assertNull($previousInvitation->used_at);

        $newInvitation = UserInvitation::query()
            ->where('user_id', $user->getKey())
            ->whereNull('revoked_at')
            ->whereNull('used_at')
            ->firstOrFail();

        $this->assertNotSame(
            $previousInvitation->token_hash,
            $newInvitation->token_hash,
        );

        $this->assertTrue(
            $newInvitation->expires_at->between(
                now()->addMinutes(59),
                now()->addMinutes(61),
            ),
        );

        $this->assertDatabaseHas('audit_events', [
            'event_key' => 'identity.user.invitation_created',
            'subject_id' => $user->getKey(),
        ]);

        $this->assertDatabaseHas('audit_events', [
            'event_key' => 'identity.user.invitation_sent',
            'subject_id' => $user->getKey(),
        ]);

        Notification::assertSentOnDemand(
            \App\Domains\Identity\UserManagement\Notifications\UserInvitationNotification::class,
        );
    }

    public function test_sending_invitation_to_active_unverified_user_is_allowed(): void
    {
        Notification::fake();

        $user = User::factory()->create([
            'status' => UserStatus::Active,
            'email_verified_at' => null,
        ]);

        app(UserInvitationService::class)->sendInvitation($user);

        $this->assertDatabaseHas('user_invitations', [
            'user_id' => $user->getKey(),
            'used_at' => null,
            'revoked_at' => null,
        ]);

        Notification::assertSentOnDemand(
            \App\Domains\Identity\UserManagement\Notifications\UserInvitationNotification::class,
        );
    }

    public function test_sending_invitation_to_inactive_user_is_rejected(): void
    {
        $user = User::factory()->create([
            'status' => UserStatus::Inactive,
            'email_verified_at' => null,
        ]);

        try {
            app(UserInvitationService::class)->sendInvitation($user);

            $this->fail(
                'The service should reject invitations to inactive users.',
            );
        } catch (\DomainException $exception) {
            $this->assertSame(
                'Only active or pending, unverified users can receive an invitation.',
                $exception->getMessage(),
            );
        }

        $this->assertDatabaseMissing('user_invitations', [
            'user_id' => $user->getKey(),
        ]);

        $this->assertDatabaseMissing('audit_events', [
            'event_key' => 'identity.user.invitation_created',
            'subject_id' => $user->getKey(),
        ]);

        $this->assertDatabaseHas('users', [
            'id' => $user->getKey(),
            'status' => UserStatus::Inactive->value,
        ]);
    }

    public function test_failed_delivery_keeps_user_pending_and_revokes_invitation(): void
    {
        $user = User::factory()->create([
            'status' => UserStatus::Pending,
            'email_verified_at' => null,
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
