<?php

declare(strict_types=1);

namespace Tests\Framework\Domains\Identity\UserManagement\Services;

use App\Domains\Identity\Authentication\Models\AuthenticationSession;
use App\Domains\Identity\Authentication\Models\PersonalAccessToken;
use App\Domains\Identity\Authentication\Services\SessionService;
use App\Domains\Identity\Enums\UserStatus;
use App\Domains\Identity\UserManagement\Services\UserManagementService;
use App\Domains\Identity\Authorization\Models\Role;
use App\Domains\Cooperatives\Models\Cooperative;
use App\Domains\Countries\Models\Country;
use App\Domains\Localization\Models\Address;
use App\Domains\Identity\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class UserLifecycleServiceTest extends TestCase
{
    use RefreshDatabase;

    private UserManagementService $service;

    private SessionService $sessions;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(UserManagementService::class);
        $this->sessions = app(SessionService::class);
    }

    public function test_root_can_deactivate_user_and_revoke_all_credentials(): void
    {
        $root = $this->userWithRole('super_admin_root');
        $target = $this->userWithRole('user');
        $sessionA = $this->sessions->create($target, 'lifecycle-session-a');
        $sessionB = $this->sessions->create($target, 'lifecycle-session-b');
        $newToken = $target->createToken('ERP');
        $token = $newToken->accessToken;

        $updated = $this->service->deactivateUser($root, $target);

        self::assertSame(UserStatus::Inactive, $updated->status);
        self::assertNotNull($sessionA->fresh()->revoked_at);
        self::assertNotNull($sessionB->fresh()->revoked_at);
        self::assertNotNull($token->fresh()->revoked_at);
        self::assertNull(PersonalAccessToken::findToken($newToken->plainTextToken));
        self::assertSame(
            2,
            \App\Domains\Identity\Authentication\Models\LoginHistory::query()
                ->where('user_id', $target->getKey())
                ->where('event', 'session_revoked')
                ->count(),
        );
        $this->assertDatabaseHas('audit_events', [
            'event_key' => 'identity.user.status_changed',
            'subject_id' => $target->getKey(),
        ]);
        $this->assertDatabaseHas('audit_events', [
            'event_key' => 'identity.authentication.api_token_revoked',
            'subject_id' => $token->getKey(),
        ]);
    }

    public function test_super_admin_can_activate_pending_user_in_assigned_cooperative(): void
    {
        $cooperativeId = $this->createCooperative()->getKey();

        $actor = $this->userWithRole('super_admin');
        $target = $this->userWithRole('user', $cooperativeId, UserStatus::Pending);
        $actor->cooperatives()->attach($cooperativeId);

        $updated = $this->service->activateUser($actor, $target);

        self::assertSame(UserStatus::Active, $updated->status);
    }

    public function test_super_admin_cannot_reactivate_suspended_user(): void
    {
        $cooperativeId = $this->createCooperative()->getKey();

        $actor = $this->userWithRole('super_admin');
        $target = $this->userWithRole('user', $cooperativeId, UserStatus::Suspended);
        $actor->cooperatives()->attach($cooperativeId);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('The user cannot transition from "suspended" to "active".');

        $this->service->activateUser($actor, $target);
    }

    public function test_cooperative_admin_cannot_change_user_status(): void
    {
        $actor = $this->userWithRole('cooperative_admin');
        $target = $this->userWithRole('user');

        $this->expectException(\DomainException::class);
        $this->service->deactivateUser($actor, $target);
    }

    public function test_super_admin_cannot_change_own_status(): void
    {
        $actor = $this->userWithRole('super_admin');

        $this->expectException(\DomainException::class);
        $this->service->deactivateUser($actor, $actor);
    }

    public function test_only_root_can_archive_and_restore_archived_user(): void
    {
        $root = $this->userWithRole('super_admin_root');
        $target = $this->userWithRole('user');
        $session = $this->sessions->create($target, 'archive-session');
        $newToken = $target->createToken('Mobile');
        $token = $newToken->accessToken;

        $archived = $this->service->archiveUser($root, $target);

        self::assertSame(UserStatus::Archived, $archived->status);
        self::assertNotNull($session->fresh()->revoked_at);
        self::assertNotNull($token->fresh()->revoked_at);
        self::assertNull(PersonalAccessToken::findToken($newToken->plainTextToken));

        $restored = $this->service->restoreArchivedUser($root, $archived);

        self::assertSame(UserStatus::Active, $restored->status);
        self::assertNotNull($session->fresh()->revoked_at);
        self::assertNotNull($token->fresh()->revoked_at);
    }

    public function test_root_can_soft_delete_and_restore_user_without_restoring_credentials(): void
    {
        $root = $this->userWithRole('super_admin_root');
        $target = $this->userWithRole('user');
        $session = $this->sessions->create($target, 'delete-session');
        $token = $target->createToken('Integration')->accessToken;

        self::assertTrue($this->service->deleteUser($root, $target));
        self::assertSoftDeleted('users', ['id' => $target->getKey()]);
        self::assertNotNull($session->fresh()->revoked_at);
        self::assertNotNull($token->fresh()->revoked_at);

        $restored = $this->service->restoreDeletedUser($root, $target);

        self::assertFalse($restored->trashed());
        self::assertSame(UserStatus::Active, $restored->status);
        self::assertNotNull($session->fresh()->revoked_at);
        self::assertNotNull($token->fresh()->revoked_at);
    }

    private function createCooperative(): Cooperative
    {
        $country = Country::factory()->create();
        $address = Address::factory()->create([
            'country_id' => $country->getKey(),
        ]);

        return Cooperative::factory()->create([
            'address_id' => $address->getKey(),
        ]);
    }

    private function userWithRole(
        string $roleSlug,
        ?string $cooperativeId = null,
        UserStatus $status = UserStatus::Active,
    ): User {
        $user = User::factory()->create([
            'cooperative_id' => $cooperativeId,
            'status' => $status,
        ]);

        $role = Role::query()->firstOrCreate(
            ['slug' => $roleSlug],
            ['name' => $roleSlug, 'is_active' => true],
        );

        $user->roles()->syncWithoutDetaching([$role->getKey()]);

        return $user;
    }
}
