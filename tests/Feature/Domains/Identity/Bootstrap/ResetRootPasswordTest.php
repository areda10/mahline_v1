<?php

declare(strict_types=1);

namespace Tests\Feature\Domains\Identity\Bootstrap;

use App\Domains\Identity\Authorization\Models\Role;
use App\Domains\Identity\Enums\UserStatus;
use App\Domains\Identity\Users\Models\User;
use Illuminate\Console\Command;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

final class ResetRootPasswordTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_stops_when_the_email_does_not_exist_without_database_changes(): void
    {
        $this->artisan('mahline:reset-root-password')
            ->expectsQuestion('Adresse e-mail du compte Root', 'missing@example.test')
            ->expectsOutputToContain('Aucun compte ne correspond')
            ->assertExitCode(Command::FAILURE);

        $this->assertDatabaseCount('users', 0);
        $this->assertDatabaseCount('audit_events', 0);
    }

    public function test_it_refuses_an_account_without_the_root_role(): void
    {
        User::factory()->create(['email' => 'user@example.test']);

        $this->artisan('mahline:reset-root-password')
            ->expectsQuestion('Adresse e-mail du compte Root', 'user@example.test')
            ->expectsOutputToContain('ne possède pas le rôle super_admin_root')
            ->assertExitCode(Command::FAILURE);

        $this->assertDatabaseCount('audit_events', 0);
    }

    public function test_a_root_account_cannot_be_soft_deleted_directly(): void
    {
        $root = $this->createRoot();

        try {
            $root->delete();
            self::fail('The super admin root account must not be deleted.');
        } catch (\DomainException $exception) {
            self::assertSame('The super admin root account cannot be deleted.', $exception->getMessage());
        }

        try {
            $root->forceDelete();
            self::fail('The super admin root account must not be force-deleted.');
        } catch (\DomainException $exception) {
            self::assertSame('The super admin root account cannot be deleted.', $exception->getMessage());
        }

        $this->assertDatabaseHas('users', [
            'id' => $root->getKey(),
            'deleted_at' => null,
        ]);
        $this->assertDatabaseCount('audit_events', 0);
    }

    public function test_inactive_root_account_cannot_be_soft_deleted_or_force_deleted(): void
    {
        $root = $this->createRoot();
        // Simulate a legacy corrupted row; new Root accounts are always bootstrapped active.
        $root->forceFill(['status' => UserStatus::Inactive])->saveQuietly();

        try {
            $root->delete();
            self::fail('An inactive super admin root account must remain permanent.');
        } catch (\DomainException $exception) {
            self::assertSame('The super admin root account cannot be deleted.', $exception->getMessage());
        }

        try {
            $root->forceDelete();
            self::fail('An inactive super admin root account must not be force-deleted.');
        } catch (\DomainException $exception) {
            self::assertSame('The super admin root account cannot be deleted.', $exception->getMessage());
        }

        $this->assertDatabaseHas('users', [
            'id' => $root->getKey(),
            'deleted_at' => null,
        ]);
        $this->assertDatabaseCount('audit_events', 0);
    }

    public function test_root_status_cannot_be_changed_through_direct_eloquent_updates(): void
    {
        $root = $this->createRoot();

        foreach ([
            UserStatus::Pending,
            UserStatus::Inactive,
            UserStatus::Suspended,
            UserStatus::Archived,
        ] as $status) {
            $root->status = $status;

            try {
                $root->save();
                self::fail('The Root account status must remain active.');
            } catch (\DomainException $exception) {
                self::assertSame(
                    'The super admin root account status cannot be changed.',
                    $exception->getMessage(),
                );
            }

            $root->refresh();
            self::assertSame(UserStatus::Active, $root->status);
        }

        // The status guard must not block permitted profile credentials changes.
        $root->email = 'root-updated@example.test';
        $root->password = 'UpdatedRootPassword123!';
        $root->save();

        $root->refresh();
        self::assertSame(UserStatus::Active, $root->status);
        self::assertSame('root-updated@example.test', $root->email);
        self::assertTrue(Hash::check('UpdatedRootPassword123!', $root->password));
    }

    public function test_it_refuses_a_soft_deleted_root_account(): void
    {
        $root = $this->createRoot();

        // Simulate a legacy/corrupt soft-deleted record without invoking normal deletion.
        $root->forceFill(['deleted_at' => now()])->saveQuietly();

        $this->artisan('mahline:reset-root-password')
            ->expectsQuestion('Adresse e-mail du compte Root', 'root@example.test')
            ->expectsOutputToContain('Ce compte est supprimé')
            ->assertExitCode(Command::FAILURE);

        $this->assertDatabaseCount('audit_events', 0);
    }

    public function test_it_refuses_a_password_shorter_than_twelve_characters(): void
    {
        $root = $this->createRoot();

        $this->artisan('mahline:reset-root-password')
            ->expectsQuestion('Adresse e-mail du compte Root', 'ROOT@EXAMPLE.TEST')
            ->expectsQuestion('Nouveau mot de passe', 'short')
            ->expectsQuestion('Confirmer le nouveau mot de passe', 'short')
            ->expectsOutputToContain('at least 12 characters')
            ->assertExitCode(Command::FAILURE);

        $this->assertTrue(Hash::check('OriginalRootPassword123!', $root->fresh()->password));
        $this->assertDatabaseCount('audit_events', 0);
    }

    public function test_it_refuses_a_mismatched_password_confirmation(): void
    {
        $root = $this->createRoot();

        $this->artisan('mahline:reset-root-password')
            ->expectsQuestion('Adresse e-mail du compte Root', 'root@example.test')
            ->expectsQuestion('Nouveau mot de passe', 'NewRootPassword123!')
            ->expectsQuestion('Confirmer le nouveau mot de passe', 'DifferentPassword123!')
            ->assertExitCode(Command::FAILURE);

        $this->assertTrue(Hash::check('OriginalRootPassword123!', $root->fresh()->password));
        $this->assertDatabaseCount('audit_events', 0);
    }

    public function test_it_changes_only_the_password_and_records_an_audit_event(): void
    {
        $root = $this->createRoot();
        $oldEmailVerifiedAt = $root->email_verified_at;

        $this->artisan('mahline:reset-root-password')
            ->expectsQuestion('Adresse e-mail du compte Root', 'ROOT@EXAMPLE.TEST')
            ->expectsQuestion('Nouveau mot de passe', 'NewRootPassword123!')
            ->expectsQuestion('Confirmer le nouveau mot de passe', 'NewRootPassword123!')
            ->expectsOutputToContain('mot de passe du compte Root a été réinitialisé')
            ->assertExitCode(Command::SUCCESS);

        $root->refresh();

        $this->assertTrue(Hash::check('NewRootPassword123!', $root->password));
        $this->assertSame(UserStatus::Active, $root->status);
        $this->assertEquals($oldEmailVerifiedAt, $root->email_verified_at);
        $this->assertTrue($root->roles()->where('slug', 'super_admin_root')->exists());
        $this->assertDatabaseHas('audit_events', [
            'event_key' => 'identity.user.root_password_reset',
            'subject_id' => $root->getKey(),
        ]);
    }

    public function test_reset_password_allows_an_active_verified_root_to_login_with_the_new_password(): void
    {
        $root = $this->createRoot([
            'status' => UserStatus::Active,
            'email_verified_at' => now(),
        ]);

        // Réinitialiser le mot de passe
        $this->artisan('mahline:reset-root-password')
            ->expectsQuestion('Adresse e-mail du compte Root', 'root@example.test')
            ->expectsQuestion('Nouveau mot de passe', 'NewRootPassword123!')
            ->expectsQuestion('Confirmer le nouveau mot de passe', 'NewRootPassword123!')
            ->assertExitCode(Command::SUCCESS);

        // Se connecter avec le nouveau mot de passe
        $this->postJson(route('authentication.login.store'), [
            'email' => $root->email,
            'password' => 'NewRootPassword123!',
        ])->assertOk()
            ->assertJson([
                'message' => 'Authentication successful.',
            ]);

        // Vérifier l'authentification
        $this->assertAuthenticatedAs($root->fresh());

        // Vérifier la création d'une session active
        $this->assertDatabaseHas('authentication_sessions', [
            'user_id' => $root->getKey(),
            'revoked_at' => null,
        ]);
    }

    private function createRoot(array $attributes = []): User
    {
        $role = Role::query()->create([
            'slug' => 'super_admin_root',
            'name' => 'Super Admin Root',
            'is_active' => true,
        ]);

        $root = User::factory()->create(array_merge([
            'email' => 'root@example.test',
            'password' => 'OriginalRootPassword123!',
            'status' => UserStatus::Active,
        ], $attributes));

        $root->roles()->attach($role->getKey());

        return $root;
    }
}
