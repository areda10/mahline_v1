<?php

declare(strict_types=1);

namespace Tests\Feature\Domains\Notifications;

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
            $user = User::factory()->create([
                'email' => $roleSlug.'@example.com', 'status' => UserStatus::Active, 'email_verified_at' => now(),
            ]);
            $role = Role::query()->firstOrCreate(['slug' => $roleSlug], [
                'name' => str($roleSlug)->replace('_', ' ')->title()->toString(), 'is_active' => true,
            ]);
            $user->roles()->syncWithoutDetaching([$role->getKey()]);
            $notification = $user->notifications()->create([
                'id' => (string) Str::uuid(), 'type' => 'profile_notice',
                'data' => ['title' => 'Votre profil est disponible'],
            ]);
            $this->actingAs($user)->get(route('notifications.index'))->assertOk()
                ->assertSee('Votre profil est disponible')->assertSee('Notifications du compte');
            $html = view('notifications._bell')->render();
            $this->assertStringContainsString('mahline-notification-icon', $html);
            $this->assertStringContainsString('Votre profil est disponible', $html);
            $this->actingAs($user)->post(route('notifications.read', $notification->getKey()))->assertRedirect();
            $this->assertNotNull($notification->fresh()->read_at);
        }
    }

    public function test_internal_notification_center_never_exposes_another_users_notifications(): void
    {
        $owner = $this->userWithRole('user');
        $other = $this->userWithRole('cooperative_admin');
        $other->notifications()->create([
            'id' => (string) Str::uuid(), 'type' => 'private_notice',
            'data' => ['title' => 'Message strictement privé'],
        ]);
        $this->actingAs($owner)->get(route('notifications.index'))->assertOk()
            ->assertDontSee('Message strictement privé');
    }

    private function userWithRole(string $slug): User
    {
        $user = User::factory()->create([
            'email' => $slug.'-'.Str::lower(Str::random(6)).'@example.com',
            'status' => UserStatus::Active, 'email_verified_at' => now(),
        ]);
        $role = Role::query()->firstOrCreate(['slug' => $slug], [
            'name' => str($slug)->replace('_', ' ')->title()->toString(), 'is_active' => true,
        ]);
        $user->roles()->syncWithoutDetaching([$role->getKey()]);

        return $user;
    }
}
