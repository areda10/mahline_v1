<?php

declare(strict_types=1);

namespace Tests\Framework\Domains\Cooperatives\Services;

use App\Domains\Audit\Models\AuditEvent;
use App\Domains\Cooperatives\Enums\CooperativeStatus;
use App\Domains\Cooperatives\Models\Cooperative;
use App\Domains\Cooperatives\Services\CooperativeService;
use App\Domains\Localization\Models\Country;
use App\Domains\Identity\Authentication\Services\SessionService;
use App\Domains\Identity\Enums\UserStatus;
use App\Domains\Identity\Authorization\Models\Permission;
use App\Domains\Identity\Authorization\Models\Role;
use App\Domains\Identity\Users\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

final class CooperativeServiceTest extends TestCase
{
    use RefreshDatabase;

    private CooperativeService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(CooperativeService::class);
    }

    public function test_create_persists_cooperative_and_audit_event(): void
    {
        $actor = $this->rootActor();
        $cooperative = $this->service->create((string) $actor->getKey(), $this->attributes(), $this->addressAttributes());

        $this->assertDatabaseHas('cooperatives', [
            'id' => $cooperative->id,
            'name' => 'Coopérative Audit',
            'created_by' => $actor->id,
        ]);
        $this->assertDatabaseHas('addresses', [
            'cooperative_id' => $cooperative->getKey(),
            'user_id' => null,
            'street' => '10 Avenue Hassan II',
        ]);
        $this->assertSame(1, $cooperative->address()->count());
        $this->assertAuditEvent('cooperatives.created', 'created', $actor, $cooperative);
    }

    public function test_create_rejects_a_pre_existing_address_id(): void
    {
        $actor = $this->rootActor();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('A cooperative address must be created through the Localization address contract.');

        $this->service->create(
            $actor,
            [...$this->attributes(), 'address_id' => '01H00000000000000000000000'],
            $this->addressAttributes(),
        );
    }

    public function test_super_admin_creator_is_automatically_assigned_to_created_cooperative(): void
    {
        $actor = $this->superAdminActor();
        $existingToken = $actor->createToken('before-cooperative-creation');

        $cooperative = $this->service->create((string) $actor->getKey(), $this->attributes(), $this->addressAttributes());

        $this->assertDatabaseHas('super_admin_cooperative', [
            'super_admin_id' => $actor->getKey(),
            'cooperative_id' => $cooperative->getKey(),
        ]);
        $assignment = DB::table('super_admin_cooperative')
            ->where('super_admin_id', $actor->getKey())
            ->where('cooperative_id', $cooperative->getKey())
            ->first();
        self::assertNotNull($assignment?->created_at);
        self::assertNotNull($assignment?->updated_at);
        $this->assertNotNull($existingToken->accessToken->fresh()->revoked_at);
        $this->assertDatabaseHas('audit_events', [
            'event_key' => 'identity.super_admin.cooperative_assigned',
            'actor_id' => $actor->getKey(),
            'subject_id' => $actor->getKey(),
            'cooperative_id' => $cooperative->getKey(),
        ]);
        $this->assertTrue(
            $actor->cooperatives()->whereKey($cooperative->getKey())->exists(),
        );
        $this->assertTrue(
            Gate::forUser($actor)->allows('view', $cooperative),
        );
    }

    public function test_root_creator_does_not_receive_a_super_admin_pivot_assignment(): void
    {
        $actor = $this->rootActor();

        $cooperative = $this->service->create((string) $actor->getKey(), $this->attributes(), $this->addressAttributes());

        $this->assertDatabaseMissing('super_admin_cooperative', [
            'super_admin_id' => $actor->getKey(),
            'cooperative_id' => $cooperative->getKey(),
        ]);
    }

    public function test_each_created_cooperative_gets_exactly_one_super_admin_assignment(): void
    {
        $actor = $this->superAdminActor();

        $first = $this->service->create((string) $actor->getKey(), $this->attributes(), $this->addressAttributes());
        $second = $this->service->create((string) $actor->getKey(), [
            ...$this->attributes(),
            'name' => 'Deuxième coopérative',
            'email' => 'second-coop@example.test',
        ], $this->addressAttributes());

        $this->assertSame(
            1,
            DB::table('super_admin_cooperative')
                ->where('super_admin_id', $actor->getKey())
                ->where('cooperative_id', $first->getKey())
                ->count(),
        );
        $this->assertSame(
            1,
            DB::table('super_admin_cooperative')
                ->where('super_admin_id', $actor->getKey())
                ->where('cooperative_id', $second->getKey())
                ->count(),
        );
    }

    public function test_failed_super_admin_assignment_rolls_back_cooperative_and_audit(): void
    {
        $actor = $this->superAdminActor();
        $failureInjected = false;

        DB::connection()->beforeExecuting(
            function (string $query) use (&$failureInjected): void {
                if (
                    ! $failureInjected
                    && preg_match('/^\\s*insert\\s+into\\s+[\\x60\\"]?super_admin_cooperative[\\x60\\"]?/i', $query) === 1
                ) {
                    $failureInjected = true;
                    throw new \RuntimeException('Simulated assignment insert failure.');
                }
            },
        );

        try {
            $this->service->create((string) $actor->getKey(), $this->attributes(), $this->addressAttributes());
            $this->fail('The failed assignment must abort cooperative creation.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('Simulated assignment insert failure.', $exception->getMessage());
        }

        $this->assertTrue($failureInjected, 'The assignment insert was not intercepted.');
        $this->assertDatabaseMissing('cooperatives', ['name' => 'Coopérative Audit']);
        $this->assertDatabaseMissing('audit_events', ['event_key' => 'cooperatives.created']);
        $this->assertDatabaseMissing('super_admin_cooperative', [
            'super_admin_id' => $actor->getKey(),
        ]);
    }

    public function test_missing_morocco_configuration_rolls_back_cooperative_creation(): void
    {
        $actor = $this->rootActor();

        try {
            $this->service->create((string) $actor->getKey(), $this->attributes(), [
                'street' => '10 Avenue Hassan II',
                'city' => 'Casablanca',
                'region' => 'Casablanca-Settat',
            ]);
            $this->fail('Cooperative creation must fail when Morocco is not configured.');
        } catch (ValidationException) {
            $this->assertDatabaseMissing('cooperatives', [
                'name' => 'Coopérative Audit',
            ]);
            $this->assertDatabaseMissing('audit_events', [
                'event_key' => 'cooperatives.created',
            ]);
            $this->assertDatabaseMissing('addresses', [
                'street' => '10 Avenue Hassan II',
            ]);
        }
    }

    public function test_create_excludes_confidential_fields_from_audit(): void
    {
        $actor = $this->rootActor();
        $cooperative = $this->service->create((string) $actor->getKey(), $this->attributes(), $this->addressAttributes());

        $event = AuditEvent::query()
            ->where('event_key', 'cooperatives.created')
            ->where('subject_id', $cooperative->id)
            ->firstOrFail();

        foreach (['rib', 'tax_id', 'ice'] as $field) {
            $this->assertArrayNotHasKey($field, $event->new_values);
        }
    }

    public function test_update_records_old_and_new_safe_values(): void
    {
        $actor = $this->rootActor();
        $cooperative = Cooperative::factory()->create(['name' => 'Ancien nom']);

        $this->service->update((string) $actor->getKey(), $cooperative, [
            'name' => 'Nouveau nom',
            'rib' => '987654321098765432109876',
        ]);

        $event = AuditEvent::query()
            ->where('event_key', 'cooperatives.updated')
            ->where('subject_id', $cooperative->id)
            ->firstOrFail();

        $this->assertSame('Ancien nom', $event->old_values['name']);
        $this->assertSame('Nouveau nom', $event->new_values['name']);
        $this->assertArrayNotHasKey('rib', $event->old_values);
        $this->assertArrayNotHasKey('rib', $event->new_values);
        $this->assertSame($actor->id, $event->actor_id);
        $this->assertDatabaseHas('cooperatives', [
            'id' => $cooperative->id,
            'updated_by' => $actor->id,
            'rib' => '987654321098765432109876',
        ]);
    }

    public function test_cooperative_admin_can_update_only_phone_email_and_address(): void
    {
        $cooperative = Cooperative::factory()->create([
            'name' => 'Nom protégé',
            'phone' => '+212600000000',
            'email' => 'old@example.test',
        ]);
        $actor = $this->cooperativeAdminActor($cooperative);

        $updated = $this->service->update((string) $actor->getKey(), $cooperative, [
            'phone' => '+212611111111',
            'email' => 'new@example.test',
        ]);

        $this->assertSame('+212611111111', $updated->phone);
        $this->assertSame('new@example.test', $updated->email);
        $this->assertSame('Nom protégé', $updated->name);

        try {
            $this->service->update((string) $actor->getKey(), $cooperative, ['name' => 'Nom modifié']);
            $this->fail('A cooperative_admin must not change the cooperative name.');
        } catch (AuthorizationException) {
            $this->assertSame('Nom protégé', $cooperative->fresh()->name);
        }
    }

    public function test_update_with_same_values_does_not_record_event(): void
    {
        $actor = $this->rootActor();
        $cooperative = Cooperative::factory()->create(['name' => 'Identique']);

        $this->service->update((string) $actor->getKey(), $cooperative, ['name' => 'Identique']);

        $this->assertDatabaseMissing('audit_events', [
            'event_key' => 'cooperatives.updated',
            'subject_id' => $cooperative->id,
        ]);
    }

    public function test_update_rejects_status_and_unknown_attributes(): void
    {
        $actor = $this->rootActor();
        $cooperative = Cooperative::factory()->create();

        $this->expectException(\InvalidArgumentException::class);
        $this->service->update((string) $actor->getKey(), $cooperative, [
            'status' => CooperativeStatus::ACTIVE,
        ]);
    }

    public function test_activate_records_status_transition(): void
    {
        $this->assertStatusTransition(CooperativeStatus::PENDING, CooperativeStatus::ACTIVE,
            'activate', 'cooperatives.activated', 'activated');
    }

    public function test_deactivating_cooperative_revokes_member_sessions_and_tokens_without_changing_identity_status(): void
    {
        $root = $this->rootActor();
        $cooperative = Cooperative::factory()->create([
            'status' => CooperativeStatus::ACTIVE,
        ]);

        $cooperativeAdmin = $this->cooperativeMember($cooperative, 'cooperative_admin');
        $user = $this->cooperativeMember($cooperative, 'user');
        $rootSession = app(SessionService::class)->create(
            $root,
            'root-session-'.uniqid(),
        );
        $adminSession = app(SessionService::class)->create(
            $cooperativeAdmin,
            'cooperative-admin-session-'.uniqid(),
        );
        $userSession = app(SessionService::class)->create(
            $user,
            'cooperative-user-session-'.uniqid(),
        );
        $adminToken = $cooperativeAdmin->createToken('admin-device')->accessToken;
        $userToken = $user->createToken('user-device')->accessToken;
        $rootToken = $root->createToken('root-device')->accessToken;

        $this->actingAs($root);
        $this->service->deactivate((string) $root->getKey(), $cooperative);

        foreach ([$cooperativeAdmin, $user] as $member) {
            self::assertSame(UserStatus::Active, $member->fresh()->status);
        }

        $this->assertDatabaseHas('authentication_sessions', [
            'id' => $adminSession->getKey(),
            'revocation_reason' => 'cooperative_inactive',
        ]);
        $this->assertDatabaseHas('authentication_sessions', [
            'id' => $userSession->getKey(),
            'revocation_reason' => 'cooperative_inactive',
        ]);
        self::assertNotNull(DB::table('personal_access_tokens')->where('id', $adminToken->getKey())->value('revoked_at'));
        self::assertNotNull(DB::table('personal_access_tokens')->where('id', $userToken->getKey())->value('revoked_at'));
        $this->assertDatabaseHas('authentication_sessions', [
            'id' => $rootSession->getKey(),
            'revoked_at' => null,
        ]);
        $this->assertDatabaseHas('personal_access_tokens', [
            'id' => $rootToken->getKey(),
            'revoked_at' => null,
        ]);
    }

    public function test_deactivate_records_status_transition(): void
    {
        $this->assertStatusTransition(CooperativeStatus::ACTIVE, CooperativeStatus::INACTIVE,
            'deactivate', 'cooperatives.deactivated', 'deactivated');
    }

    public function test_suspend_records_status_transition(): void
    {
        $this->assertStatusTransition(CooperativeStatus::ACTIVE, CooperativeStatus::SUSPENDED,
            'suspend', 'cooperatives.suspended', 'suspended');
    }

    public function test_archive_records_status_transition(): void
    {
        $this->assertStatusTransition(CooperativeStatus::INACTIVE, CooperativeStatus::ARCHIVED,
            'archive', 'cooperatives.archived', 'archived');
    }

    public function test_repeating_same_status_does_not_duplicate_event(): void
    {
        $actor = $this->rootActor();
        $cooperative = Cooperative::factory()->active()->create();

        $this->service->activate((string) $actor->getKey(), $cooperative);

        $this->assertDatabaseMissing('audit_events', [
            'event_key' => 'cooperatives.activated',
            'subject_id' => $cooperative->id,
        ]);
    }

    public function test_delete_soft_deletes_and_records_actor(): void
    {
        $actor = $this->rootActor();
        $cooperative = Cooperative::factory()->create();

        $this->service->delete((string) $actor->getKey(), $cooperative);

        $this->assertSoftDeleted('cooperatives', ['id' => $cooperative->id]);
        $this->assertDatabaseHas('cooperatives', [
            'id' => $cooperative->id,
            'deleted_by' => $actor->id,
        ]);
        $this->assertAuditEvent('cooperatives.deleted', 'deleted', $actor, $cooperative);
    }

    public function test_deleting_cooperative_revokes_member_access_and_restore_does_not_restore_old_access(): void
    {
        $root = $this->rootActor();
        $cooperative = Cooperative::factory()->create([
            'status' => CooperativeStatus::ACTIVE,
        ]);
        $member = $this->cooperativeMember($cooperative, 'user');
        $session = app(SessionService::class)->create($member, 'deleted-coop-session-'.uniqid());
        $token = $member->createToken('deleted-coop-device')->accessToken;

        $this->actingAs($root);
        $this->service->delete((string) $root->getKey(), $cooperative);

        $this->assertDatabaseHas('authentication_sessions', [
            'id' => $session->getKey(),
            'revocation_reason' => 'cooperative_deleted',
        ]);
        self::assertNotNull(DB::table('personal_access_tokens')->where('id', $token->getKey())->value('revoked_at'));
        self::assertSame(UserStatus::Active, $member->fresh()->status);

        $this->service->restore((string) $root->getKey(), $cooperative);
        self::assertNotNull(DB::table('personal_access_tokens')->where('id', $token->getKey())->value('revoked_at'));
        self::assertNotNull($session->fresh()->revoked_at);
    }

    public function test_deleting_already_deleted_cooperative_is_idempotent(): void
    {
        $actor = $this->rootActor();
        $cooperative = Cooperative::factory()->create();
        $cooperative->delete();

        $this->service->delete((string) $actor->getKey(), $cooperative);

        $this->assertDatabaseMissing('audit_events', [
            'event_key' => 'cooperatives.deleted',
            'subject_id' => $cooperative->id,
        ]);
    }

    public function test_create_rolls_back_when_audit_insert_fails(): void
    {
        $actor = $this->rootActor();
        $failureInjected = false;

        DB::connection()->beforeExecuting(
            function (string $query) use (&$failureInjected): void {
                if (
                    ! $failureInjected
                    && preg_match(
                        '/^\\s*insert\\s+into\\s+[`"]?audit_events[`"]?/i',
                        $query,
                    ) === 1
                ) {
                    $failureInjected = true;

                    throw new \RuntimeException('Simulated audit insert failure.');
                }
            },
        );

        try {
            $this->service->create((string) $actor->getKey(), $this->attributes(), $this->addressAttributes());
            $this->fail('The audit insert failure should abort cooperative creation.');
        } catch (\RuntimeException $exception) {
            $this->assertSame(
                'Simulated audit insert failure.',
                $exception->getMessage(),
            );
        }

        $this->assertTrue($failureInjected, 'The audit insert was not intercepted.');
        $this->assertDatabaseMissing('cooperatives', [
            'name' => 'Coopérative Audit',
        ]);
        $this->assertDatabaseMissing('audit_events', [
            'event_key' => 'cooperatives.created',
        ]);
    }

    public function test_outer_transaction_rolls_back_cooperative_and_audit_event(): void
    {
        $actor = $this->rootActor();

        try {
            DB::transaction(function () use ($actor): void {
                $this->service->create((string) $actor->getKey(), $this->attributes(), $this->addressAttributes());
                throw new \RuntimeException('Rollback requested by test.');
            });
        } catch (\RuntimeException $exception) {
            $this->assertSame('Rollback requested by test.', $exception->getMessage());
        }

        $this->assertDatabaseMissing('cooperatives', ['name' => 'Coopérative Audit']);
        $this->assertDatabaseMissing('audit_events', ['event_key' => 'cooperatives.created']);
    }

    public function test_assigned_super_admin_can_delete_only_its_assigned_cooperative(): void
    {
        $actor = $this->superAdminActor();
        $assigned = Cooperative::factory()->create();
        $unassigned = Cooperative::factory()->create();
        $actor->cooperatives()->attach($assigned->getKey());
        $assignedAddress = $assigned->address;

        $this->service->delete((string) $actor->getKey(), $assigned);

        $this->assertSoftDeleted('cooperatives', ['id' => $assigned->getKey()]);
        $this->assertSoftDeleted('addresses', ['id' => $assignedAddress->getKey()]);
        $this->assertAuditEvent('cooperatives.deleted', 'deleted', $actor, $assigned);

        try {
            $this->service->delete((string) $actor->getKey(), $unassigned);
            $this->fail('A super admin cannot delete a cooperative outside its assignments.');
        } catch (AuthorizationException) {
            $this->assertDatabaseHas('cooperatives', [
                'id' => $unassigned->getKey(),
                'deleted_at' => null,
            ]);
            $this->assertDatabaseMissing('audit_events', [
                'event_key' => 'cooperatives.deleted',
                'subject_id' => $unassigned->getKey(),
            ]);
        }
    }

    public function test_ordinary_actor_cannot_delete_cooperative(): void
    {
        $actor = User::factory()->create();
        $cooperative = Cooperative::factory()->create();

        try {
            $this->service->delete((string) $actor->getKey(), $cooperative);
            $this->fail('Only super_admin_root may delete a cooperative.');
        } catch (AuthorizationException) {
            $this->assertDatabaseHas('cooperatives', [
                'id' => $cooperative->id,
                'deleted_at' => null,
            ]);
            $this->assertDatabaseMissing('audit_events', [
                'event_key' => 'cooperatives.deleted',
                'subject_id' => $cooperative->id,
            ]);
        }
    }

    public function test_archived_cooperative_can_be_reactivated_by_super_admin_or_root(): void
    {
        $ordinary = User::factory()->create();
        $root = $this->rootActor();
        $cooperative = Cooperative::factory()->create([
            'status' => CooperativeStatus::ARCHIVED,
        ]);

        try {
            $this->service->activate($ordinary, $cooperative);
            $this->fail('Ordinary actor must not reactivate an archived cooperative.');
        } catch (AuthorizationException) {
            $this->assertSame(CooperativeStatus::ARCHIVED, $cooperative->fresh()->status);
            $this->assertDatabaseMissing('audit_events', [
                'subject_id' => $cooperative->id,
                'event_key' => 'cooperatives.activated',
            ]);
        }

        $superAdmin = $this->superAdminActor();
        $superAdmin->cooperatives()->attach($cooperative->id);
        $this->service->deactivate((string) $superAdmin->getKey(), $cooperative);
        $this->assertSame(CooperativeStatus::INACTIVE, $cooperative->fresh()->status);
    }

    public function test_root_can_reactivate_archived_cooperative_as_active(): void
    {
        $root = $this->rootActor();
        $cooperative = Cooperative::factory()->create([
            'status' => CooperativeStatus::ARCHIVED,
        ]);

        $this->service->activate($root, $cooperative);

        $this->assertSame(CooperativeStatus::ACTIVE, $cooperative->fresh()->status);
        $this->assertAuditEvent('cooperatives.activated', 'activated', $root, $cooperative);
    }

    public function test_disallowed_status_transition_does_not_change_status_or_write_audit(): void
    {
        $actor = $this->rootActor();
        $cooperative = Cooperative::factory()->create([
            'status' => CooperativeStatus::PENDING,
        ]);

        try {
            $this->service->suspend((string) $actor->getKey(), $cooperative);
            $this->fail('PENDING to SUSPENDED is not allowed.');
        } catch (\DomainException) {
            $this->assertSame(CooperativeStatus::PENDING, $cooperative->fresh()->status);
            $this->assertDatabaseMissing('audit_events', [
                'subject_id' => $cooperative->id,
                'event_key' => 'cooperatives.suspended',
            ]);
        }
    }

    public function test_root_can_restore_deleted_cooperative_and_its_owner_deleted_address(): void
    {
        $actor = $this->rootActor();
        $cooperative = Cooperative::factory()->create();
        $address = $cooperative->address;

        $this->service->delete((string) $actor->getKey(), $cooperative);
        $this->assertSoftDeleted('cooperatives', ['id' => $cooperative->getKey()]);
        $this->assertSoftDeleted('addresses', ['id' => $address->getKey()]);

        $restored = $this->service->restore((string) $actor->getKey(), $cooperative);

        self::assertFalse($restored->trashed());
        $this->assertDatabaseHas('addresses', [
            'id' => $address->getKey(),
            'cooperative_id' => $cooperative->getKey(),
            'deleted_at' => null,
            'deletion_source' => null,
            'is_primary' => true,
        ]);
        $this->assertAuditEvent('cooperatives.restored', 'restored', $actor, $cooperative);
    }

    private function cooperativeMember(Cooperative $cooperative, string $roleSlug): User
    {
        $member = User::factory()->create([
            'cooperative_id' => $cooperative->getKey(),
            'status' => UserStatus::Active,
            'email_verified_at' => now(),
        ]);
        $role = Role::query()->firstOrCreate(
            ['slug' => $roleSlug],
            ['name' => $roleSlug, 'is_active' => true],
        );
        $role->forceFill(['is_active' => true])->save();
        $member->roles()->attach($role->getKey());

        return $member;
    }

    private function cooperativeAdminActor(Cooperative $cooperative): User
    {
        $actor = User::factory()->create(['cooperative_id' => $cooperative->id]);
        $role = Role::query()->firstOrCreate(
            ['slug' => 'cooperative_admin'],
            ['name' => 'Cooperative Admin', 'is_active' => true],
        );
        $actor->roles()->attach($role->getKey());

        foreach (['cooperatives.view', 'cooperatives.update'] as $slug) {
            $permission = Permission::query()->firstOrCreate(
                ['slug' => $slug],
                ['name' => $slug, 'is_active' => true],
            );
            $role->permissions()->syncWithoutDetaching([$permission->getKey()]);
        }

        return $actor;
    }

    private function rootActor(): User
    {
        $actor = User::factory()->create();
        $role = Role::query()->firstOrCreate(
            ['slug' => 'super_admin_root'],
            ['name' => 'Super Admin Root', 'is_active' => true],
        );
        $actor->roles()->attach($role->getKey());

        foreach (['cooperatives.view', 'cooperatives.create', 'cooperatives.update', 'cooperatives.delete'] as $slug) {
            $permission = Permission::query()->firstOrCreate(
                ['slug' => $slug],
                ['name' => $slug, 'is_active' => true],
            );
            $role->permissions()->syncWithoutDetaching([$permission->getKey()]);
        }

        return $actor;
    }

    private function superAdminActor(): User
    {
        $actor = User::factory()->create();
        $role = Role::query()->firstOrCreate(
            ['slug' => 'super_admin'],
            ['name' => 'Super Admin', 'is_active' => true],
        );
        $actor->roles()->attach($role->getKey());

        foreach (['cooperatives.view', 'cooperatives.create', 'cooperatives.update', 'cooperatives.delete'] as $slug) {
            $permission = Permission::query()->firstOrCreate(
                ['slug' => $slug],
                ['name' => $slug, 'is_active' => true],
            );
            $role->permissions()->syncWithoutDetaching([$permission->getKey()]);
        }

        return $actor;
    }

    private function assertStatusTransition(
        CooperativeStatus $from,
        CooperativeStatus $to,
        string $method,
        string $eventKey,
        string $action,
    ): void {
        $actor = $this->rootActor();
        $cooperative = Cooperative::factory()->create(['status' => $from]);

        $updated = $this->service->{$method}((string) $actor->getKey(), $cooperative);

        $this->assertSame($to, $updated->status);
        $this->assertDatabaseHas('cooperatives', [
            'id' => $cooperative->id,
            'status' => $to->value,
            'updated_by' => $actor->id,
        ]);
        $this->assertAuditEvent($eventKey, $action, $actor, $cooperative);
    }

    private function assertAuditEvent(
        string $eventKey,
        string $action,
        User $actor,
        Cooperative $cooperative,
    ): void {
        $this->assertDatabaseHas('audit_events', [
            'event_key' => $eventKey,
            'action' => $action,
            'actor_type' => User::class,
            'actor_id' => $actor->id,
            'cooperative_id' => $cooperative->id,
            'subject_type' => Cooperative::class,
            'subject_id' => $cooperative->id,
        ]);
    }

    /** @return array<string, mixed> */
    private function addressAttributes(): array
    {
        Country::query()->firstOrCreate(
            ['code' => 'MA'],
            [
                'iso3' => 'MAR',
                'name' => 'Maroc',
                'native_name' => 'المغرب',
                'phone_code' => '+212',
                'currency' => 'MAD',
                'locale' => 'fr',
                'is_active' => true,
            ],
        );

        return [
            'street' => '10 Avenue Hassan II',
            'city' => 'Casablanca',
            'postal_code' => '20000',
            'region' => 'Casablanca-Settat',
        ];
    }

    /** @return array<string, mixed> */
    private function attributes(): array
    {
        return [
            'name' => 'Coopérative Audit',
            'rib' => '123456789012345678901234',
            'tax_id' => 'TAX-001',
            'phone' => '+212600000001',
            'email' => 'audit-coop@example.test',
            'rc' => 'RC-001',
            'ice' => '001234567890123',
        ];
    }
}
