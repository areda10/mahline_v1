<?php

declare(strict_types=1);

namespace Tests\Framework\Domains\Cooperatives\Services;

use App\Domains\Audit\Models\AuditEvent;
use App\Domains\Cooperatives\Enums\CooperativeStatus;
use App\Domains\Cooperatives\Models\Cooperative;
use App\Domains\Cooperatives\Services\CooperativeService;
use App\Domains\Identity\Authorization\Models\Role;
use App\Domains\Identity\Authorization\Models\Permission;
use App\Domains\Identity\Users\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use App\Domains\Localization\Models\Address;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
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
        $cooperative = $this->service->create($actor, $this->attributes());

        $this->assertDatabaseHas('cooperatives', [
            'id' => $cooperative->id,
            'name' => 'Coopérative Audit',
            'created_by' => $actor->id,
        ]);
        $this->assertAuditEvent('cooperatives.created', 'created', $actor, $cooperative);
    }

    public function test_create_excludes_confidential_fields_from_audit(): void
    {
        $actor = $this->rootActor();
        $cooperative = $this->service->create($actor, $this->attributes());

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

        $this->service->update($actor, $cooperative, [
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

        $updated = $this->service->update($actor, $cooperative, [
            'phone' => '+212611111111',
            'email' => 'new@example.test',
        ]);

        $this->assertSame('+212611111111', $updated->phone);
        $this->assertSame('new@example.test', $updated->email);
        $this->assertSame('Nom protégé', $updated->name);

        try {
            $this->service->update($actor, $cooperative, ['name' => 'Nom modifié']);
            $this->fail('A cooperative_admin must not change the cooperative name.');
        } catch (AuthorizationException) {
            $this->assertSame('Nom protégé', $cooperative->fresh()->name);
        }
    }

    public function test_update_with_same_values_does_not_record_event(): void
    {
        $actor = $this->rootActor();
        $cooperative = Cooperative::factory()->create(['name' => 'Identique']);

        $this->service->update($actor, $cooperative, ['name' => 'Identique']);

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
        $this->service->update($actor, $cooperative, [
            'status' => CooperativeStatus::ACTIVE,
        ]);
    }

    public function test_activate_records_status_transition(): void
    {
        $this->assertStatusTransition(CooperativeStatus::PENDING, CooperativeStatus::ACTIVE,
            'activate', 'cooperatives.activated', 'activated');
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

        $this->service->activate($actor, $cooperative);

        $this->assertDatabaseMissing('audit_events', [
            'event_key' => 'cooperatives.activated',
            'subject_id' => $cooperative->id,
        ]);
    }

    public function test_delete_soft_deletes_and_records_actor(): void
    {
        $actor = $this->rootActor();
        $cooperative = Cooperative::factory()->create();

        $this->service->delete($actor, $cooperative);

        $this->assertSoftDeleted('cooperatives', ['id' => $cooperative->id]);
        $this->assertDatabaseHas('cooperatives', [
            'id' => $cooperative->id,
            'deleted_by' => $actor->id,
        ]);
        $this->assertAuditEvent('cooperatives.deleted', 'deleted', $actor, $cooperative);
    }

    public function test_deleting_already_deleted_cooperative_is_idempotent(): void
    {
        $actor = $this->rootActor();
        $cooperative = Cooperative::factory()->create();
        $cooperative->delete();

        $this->service->delete($actor, $cooperative);

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
            $this->service->create($actor, $this->attributes());
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
                $this->service->create($actor, $this->attributes());
                throw new \\RuntimeException('Rollback requested by test.');
            });
        } catch (\\RuntimeException $exception) {
            $this->assertSame('Rollback requested by test.', $exception->getMessage());
        }

        $this->assertDatabaseMissing('cooperatives', ['name' => 'Coopérative Audit']);
        $this->assertDatabaseMissing('audit_events', ['event_key' => 'cooperatives.created']);
    }

    public function test_ordinary_actor_cannot_delete_cooperative(): void
    {
        $actor = User::factory()->create();
        $cooperative = Cooperative::factory()->create();

        try {
            $this->service->delete($actor, $cooperative);
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
        $this->service->deactivate($superAdmin, $cooperative);
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
            $this->service->suspend($actor, $cooperative);
            $this->fail('PENDING to SUSPENDED is not allowed.');
        } catch (\DomainException) {
            $this->assertSame(CooperativeStatus::PENDING, $cooperative->fresh()->status);
            $this->assertDatabaseMissing('audit_events', [
                'subject_id' => $cooperative->id,
                'event_key' => 'cooperatives.suspended',
            ]);
        }
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

        $updated = $this->service->{$method}($actor, $cooperative);

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
    private function attributes(): array
    {
        return [
            'name' => 'Coopérative Audit',
            'rib' => '123456789012345678901234',
            'tax_id' => 'TAX-001',
            'address_id' => Address::factory()->create()->id,
            'phone' => '+212600000001',
            'email' => 'audit-coop@example.test',
            'rc' => 'RC-001',
            'ice' => '001234567890123',
        ];
    }
}
