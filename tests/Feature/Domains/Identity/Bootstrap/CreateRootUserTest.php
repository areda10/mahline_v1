<?php

declare(strict_types=1);

namespace Tests\Feature\Domains\Identity\Bootstrap;

use App\Domains\Identity\Authorization\Models\Role;
use App\Domains\Identity\Enums\UserStatus;
use App\Domains\Identity\Users\Models\User;
use Illuminate\Console\Command;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class CreateRootUserTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_the_first_verified_active_root_without_cooperative_assignment(): void
    {
        $role = Role::query()->create([
            'slug' => 'super_admin_root',
            'name' => 'Super Admin Root',
            'is_active' => true,
        ]);

        $this->artisan('mahline:create-root')
            ->expectsQuestion('Prénom', 'Amina')
            ->expectsQuestion('Nom', 'Test')
            ->expectsQuestion('Adresse e-mail', 'ROOT@EXAMPLE.TEST')
            ->expectsQuestion('Téléphone', '+212600000000')
            ->expectsQuestion('Mot de passe', 'StrongRootPassword123!')
            ->expectsOutputToContain('Root account created successfully')
            ->assertExitCode(Command::SUCCESS);

        $user = User::query()
            ->where('email', 'root@example.test')
            ->firstOrFail();

        $this->assertSame('Amina', $user->first_name);
        $this->assertSame('Test', $user->last_name);
        $this->assertSame('Amina Test', $user->display_name);
        $this->assertSame(UserStatus::Active, $user->status);
        $this->assertNotNull($user->email_verified_at);
        $this->assertNull($user->cooperative_id);
        $this->assertTrue($user->roles()->whereKey($role->getKey())->exists());
        $this->assertNotSame('StrongRootPassword123!', $user->password);
        $this->assertDatabaseCount('super_admin_cooperative', 0);
        $this->assertDatabaseHas('audit_events', [
            'event_key' => 'identity.user.root_bootstrapped',
            'subject_id' => $user->getKey(),
        ]);
    }

    public function test_it_refuses_to_create_a_second_root_account(): void
    {
        $role = Role::query()->create([
            'slug' => 'super_admin_root',
            'name' => 'Super Admin Root',
            'is_active' => true,
        ]);

        $existing = User::factory()->create();
        $existing->roles()->attach($role->getKey());

        $this->artisan('mahline:create-root')
            ->expectsOutputToContain('A super_admin_root account already exists')
            ->assertExitCode(Command::FAILURE);

        $this->assertDatabaseCount('users', 1);
    }

    public function test_it_refuses_to_run_when_the_root_role_is_missing(): void
    {
        $this->artisan('mahline:create-root')
            ->expectsOutputToContain('The active super_admin_root role is missing')
            ->assertExitCode(Command::FAILURE);

        $this->assertDatabaseCount('users', 0);
    }

    public function test_it_refuses_an_email_already_used_by_another_account(): void
    {
        Role::query()->create([
            'slug' => 'super_admin_root',
            'name' => 'Super Admin Root',
            'is_active' => true,
        ]);

        User::factory()->create(['email' => 'taken@example.test']);

        $this->artisan('mahline:create-root')
            ->expectsQuestion('Prénom', 'Amina')
            ->expectsQuestion('Nom', 'Test')
            ->expectsQuestion('Adresse e-mail', 'taken@example.test')
            ->expectsQuestion('Téléphone', '+212600000000')
            ->expectsQuestion('Mot de passe', 'StrongRootPassword123!')
            ->expectsOutputToContain('already used by an account')
            ->assertExitCode(Command::FAILURE);

        $this->assertDatabaseCount('users', 1);
    }
}
