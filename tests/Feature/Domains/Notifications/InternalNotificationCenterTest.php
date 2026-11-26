<?php

declare(strict_types=1);

namespace Tests\Feature\Domains\Notifications;

use App\Domains\Cooperatives\Models\Cooperative;
use App\Domains\Identity\Authentication\Http\Middleware\EnsureActiveAuthenticationSession;
use App\Domains\Identity\Authorization\Models\Role;
use App\Domains\Identity\Enums\UserStatus;
use App\Domains\Identity\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

final class InternalNotificationCenterTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(EnsureActiveAuthenticationSession::class);
    }

    public function test_cooperative_admin_and_user_can_open_their_notification_center_and_mark_notifications_read(): void
    {
        foreach (['cooperative_admin', 'user'] as $roleSlug) {
            $user = $this->userWithRole($roleSlug);

            $notification = $user->notifications()->create([
                'id' => (string) Str::uuid(),
                'type' => 'profile_notice',
                'data' => [
                    'title' => 'Votre profil est disponible',
                    'message' => 'Une action est requise sur votre compte.',
                ],
            ]);

            $this->actingAs($user)
                ->get(route('notifications.index'))
                ->assertOk()
                ->assertSee('Votre profil est disponible')
                ->assertSee('Une action est requise sur votre compte.')
                ->assertSee('Notifications du compte');

            $html = view('notifications._bell')->render();

            $this->assertStringContainsString('mahline-notification-icon', $html);
            $this->assertStringContainsString('Votre profil est disponible', $html);
            $this->assertStringContainsString('Une action est requise sur votre compte.', $html);

            $this->actingAs($user)
                ->post(route('notifications.read', $notification->getKey()))
                ->assertRedirect();

            $this->assertNotNull($notification->fresh()->read_at);
        }
    }

    public function test_internal_notification_center_never_exposes_another_users_notifications(): void
    {
        $owner = $this->userWithRole('user');
        $other = $this->userWithRole('cooperative_admin');

        $other->notifications()->create([
            'id' => (string) Str::uuid(),
            'type' => 'private_notice',
            'data' => ['title' => 'Message strictement privé'],
        ]);

        $this->actingAs($owner)
            ->get(route('notifications.index'))
            ->assertOk()
            ->assertDontSee('Message strictement privé');
    }

    private function userWithRole(string $slug): User
    {
        $attributes = [
            'email' => $slug.'-'.Str::lower(Str::random(6)).'@example.com',
            'status' => UserStatus::Active,
            'email_verified_at' => now(),
        ];

        if (in_array($slug, ['cooperative_admin', 'user'], true)) {
            $attributes['cooperative_id'] = Cooperative::factory()
                ->active()
                ->create()
                ->getKey();
        }

        $user = User::factory()->create($attributes);

        $role = Role::query()->firstOrCreate(
            ['slug' => $slug],
            ['name' => str($slug)->replace('_', ' ')->title()->toString(), 'is_active' => true],
        );

        $user->roles()->syncWithoutDetaching([$role->getKey()]);

        return $user;
    }
}
