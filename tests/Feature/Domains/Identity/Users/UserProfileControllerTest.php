<?php

declare(strict_types=1);

namespace Tests\Feature\Domains\Identity\Users;

use App\Domains\Identity\Authentication\Http\Middleware\EnsureActiveAuthenticationSession;
use App\Domains\Identity\Authorization\Models\Role;
use App\Domains\Identity\Enums\UserStatus;
use App\Domains\Identity\Users\Models\User;
use App\Domains\Localization\Contracts\UserAddressLifecycleManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

final class UserProfileControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(EnsureActiveAuthenticationSession::class);
    }

    public function test_cooperative_admin_and_user_can_view_their_own_profile_and_address_form(): void
    {
        foreach (['cooperative_admin', 'user'] as $role) {
            $user = $this->userWithRole($role);
            $this->actingAs($user)->get(route('backoffice.profile.edit'))
                ->assertOk()->assertSee('Mon profil')->assertSee('Photo de profil')
                ->assertSee('Informations personnelles')->assertSee('Adresse personnelle')
                ->assertSee('Enregistrer mon adresse');
        }
    }

    public function test_internal_user_can_update_personal_information_without_changing_email_security_or_address(): void
    {
        $user = $this->userWithRole('user');
        $address = app(UserAddressLifecycleManager::class)->createForUser((string) $user->getKey(), (string) $user->getKey(), [
            'number' => '10', 'street' => 'Rue existante', 'city' => 'Rabat',
            'region' => 'Rabat-Salé-Kénitra', 'postal_code' => '10000',
        ]);
        $originalPassword = $user->password;
        $this->actingAs($user)->put(route('backoffice.profile.update'), [
            'first_name' => 'Nadia', 'last_name' => 'Amrani', 'telephone' => '+212 612345679',
            'email' => 'attacker@example.com', 'password' => 'Attacker123!',
            'status' => UserStatus::Inactive->value, 'cooperative_id' => '01J00000000000000000000000',
            'roles' => ['super_admin_root'],
        ])->assertRedirect(route('backoffice.profile.edit'))->assertSessionHas('success');
        $user->refresh();
        $this->assertSame('Nadia', $user->first_name);
        $this->assertSame('Amrani', $user->last_name);
        $this->assertSame('Nadia Amrani', $user->display_name);
        $this->assertSame('+212 612345679', $user->telephone);
        $this->assertSame('user@example.com', $user->email);
        $this->assertSame($originalPassword, $user->password);
        $this->assertSame(UserStatus::Active, $user->status);
        $this->assertSame($address->getKey(), $user->primaryAddress()->value('id'));
        $this->assertDatabaseHas('audit_events', ['event_key' => 'identity.user.profile_updated', 'subject_id' => $user->getKey()]);
    }

    public function test_profile_update_rejects_an_invalid_telephone_number(): void
    {
        $user = $this->userWithRole('cooperative_admin');
        $this->actingAs($user)->put(route('backoffice.profile.update'), [
            'first_name' => 'Nadia', 'last_name' => 'Amrani', 'telephone' => 'not-a-phone',
        ])->assertSessionHasErrors(['telephone']);
    }

    public function test_internal_user_can_upload_replace_and_delete_their_private_profile_photo(): void
    {
        Storage::fake('local');
        $user = $this->userWithRole('user');
        $this->actingAs($user)->post(route('backoffice.profile.photo.store'), [
            'file' => UploadedFile::fake()->image('portrait.png', 120, 120),
        ])->assertRedirect(route('backoffice.profile.edit'))->assertSessionHas('success');
        $firstPhoto = $user->profileImage()->firstOrFail();
        $this->assertSame('profile_image', $firstPhoto->category);
        $this->assertSame('private', $firstPhoto->visibility);
        $this->assertTrue($firstPhoto->is_primary);
        $this->actingAs($user)->post(route('backoffice.profile.photo.store'), [
            'file' => UploadedFile::fake()->image('new-portrait.png', 140, 140),
        ])->assertRedirect(route('backoffice.profile.edit'));
        $secondPhoto = $user->profileImage()->firstOrFail();
        $this->assertNotSame($firstPhoto->getKey(), $secondPhoto->getKey());
        $this->assertSoftDeleted('media', ['id' => $firstPhoto->getKey()]);
        $this->actingAs($user)->delete(route('backoffice.profile.photo.destroy'))
            ->assertRedirect(route('backoffice.profile.edit'));
        $this->assertSoftDeleted('media', ['id' => $secondPhoto->getKey()]);
        $this->assertNull($user->fresh()->profileImage);
    }

    public function test_profile_photo_is_private_and_visible_only_to_its_owner(): void
    {
        Storage::fake('local');
        $owner = $this->userWithRole('user');
        $other = $this->userWithRole('cooperative_admin');
        $this->actingAs($owner)->post(route('backoffice.profile.photo.store'), [
            'file' => UploadedFile::fake()->image('private.png', 120, 120),
        ])->assertRedirect(route('backoffice.profile.edit'));
        $photo = $owner->profileImage()->firstOrFail();
        $this->actingAs($owner)->get(route('media.private', $photo).'?variant=thumbnail')->assertOk();
        $this->actingAs($other)->get(route('media.private', $photo).'?variant=thumbnail')->assertForbidden();
    }

    public function test_other_roles_cannot_use_the_internal_self_service_profile_routes(): void
    {
        $superAdmin = $this->userWithRole('super_admin');
        $this->actingAs($superAdmin)->get(route('backoffice.profile.edit'))->assertForbidden();
    }

    private function userWithRole(string $slug): User
    {
        $user = User::factory()->create([
            'first_name' => 'Samira', 'last_name' => 'User', 'display_name' => 'Samira User',
            'email' => $slug.'@example.com', 'telephone' => '+212 612345678',
            'status' => UserStatus::Active, 'email_verified_at' => now(),
        ]);
        $role = Role::query()->firstOrCreate(['slug' => $slug], [
            'name' => str($slug)->replace('_', ' ')->title()->toString(), 'is_active' => true,
        ]);
        $user->roles()->syncWithoutDetaching([$role->getKey()]);

        return $user;
    }
}
