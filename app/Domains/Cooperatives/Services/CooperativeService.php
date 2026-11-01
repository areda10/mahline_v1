<?php

declare(strict_types=1);

namespace App\Domains\Cooperatives\Services;

use App\Domains\Audit\Services\AuditService;
use App\Domains\Cooperatives\Enums\CooperativeStatus;
use App\Domains\Cooperatives\Models\Cooperative;
use App\Domains\Identity\Users\Models\User;
use App\Shared\DTOs\AuditEntryDTO;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

final class CooperativeService
{
    /** @var list<string> */
    private const WRITABLE_FIELDS = [
        'name', 'rib', 'tax_id', 'address_id', 'phone', 'email', 'rc', 'ice',
    ];

    /** @var list<string> */
    private const COOPERATIVE_ADMIN_WRITABLE_FIELDS = ['phone', 'email', 'address_id'];

    /** @var list<string> */
    private const AUDITABLE_FIELDS = [
        'name', 'address_id', 'phone', 'email', 'rc', 'status',
    ];

    public function __construct(
        private readonly AuditService $audit,
    ) {
    }

    /** @param array<string, mixed> $attributes */
    public function create(User $actor, array $attributes): Cooperative
    {
        Gate::forUser($actor)->authorize('create', Cooperative::class);
        $attributes = $this->filterWritableAttributes($attributes);

        return DB::transaction(function () use ($actor, $attributes): Cooperative {
            $cooperative = new Cooperative();
            $cooperative->fill($attributes);
            $cooperative->status ??= CooperativeStatus::PENDING;
            $cooperative->created_by = (string) $actor->getKey();
            $cooperative->save();

            $this->record(
                actor: $actor,
                cooperative: $cooperative,
                eventKey: 'cooperatives.created',
                action: 'created',
                newValues: $this->auditSnapshot($cooperative),
            );

            return $cooperative;
        });
    }

    /** @param array<string, mixed> $attributes */
    public function update(
        User $actor,
        Cooperative $cooperative,
        array $attributes,
    ): Cooperative {
        $attributes = $this->filterWritableAttributes($attributes);

        return DB::transaction(function () use (
            $actor,
            $cooperative,
            $attributes,
        ): Cooperative {
            $cooperative = Cooperative::query()
                ->lockForUpdate()
                ->findOrFail($cooperative->getKey());

            Gate::forUser($actor)->authorize('update', $cooperative);

            if ($actor->hasRole('cooperative_admin')
                && array_diff(array_keys($attributes), self::COOPERATIVE_ADMIN_WRITABLE_FIELDS) !== []) {
                throw new AuthorizationException('A cooperative_admin may only update phone, email and address.');
            }

            $before = $this->auditSnapshot($cooperative);
            $cooperative->fill($attributes);

            if (! $cooperative->isDirty()) {
                return $cooperative;
            }

            $changedFields = array_keys($cooperative->getDirty());
            $cooperative->updated_by = (string) $actor->getKey();
            $cooperative->save();

            $this->record(
                actor: $actor,
                cooperative: $cooperative,
                eventKey: 'cooperatives.updated',
                action: 'updated',
                oldValues: $before,
                newValues: $this->auditSnapshot($cooperative),
                metadata: ['changed_fields' => $changedFields],
            );

            return $cooperative;
        });
    }

    public function activate(User $actor, Cooperative $cooperative): Cooperative
    {
        return $this->changeStatus($actor, $cooperative, CooperativeStatus::ACTIVE,
            'cooperatives.activated', 'activated');
    }

    public function deactivate(User $actor, Cooperative $cooperative): Cooperative
    {
        return $this->changeStatus($actor, $cooperative, CooperativeStatus::INACTIVE,
            'cooperatives.deactivated', 'deactivated');
    }

    public function suspend(User $actor, Cooperative $cooperative): Cooperative
    {
        return $this->changeStatus($actor, $cooperative, CooperativeStatus::SUSPENDED,
            'cooperatives.suspended', 'suspended');
    }

    public function archive(User $actor, Cooperative $cooperative): Cooperative
    {
        return $this->changeStatus($actor, $cooperative, CooperativeStatus::ARCHIVED,
            'cooperatives.archived', 'archived');
    }

    public function delete(User $actor, Cooperative $cooperative): void
    {
        DB::transaction(function () use ($actor, $cooperative): void {
            $cooperative = Cooperative::withTrashed()
                ->lockForUpdate()
                ->findOrFail($cooperative->getKey());

            Gate::forUser($actor)->authorize('delete', $cooperative);

            if ($cooperative->trashed()) {
                return;
            }

            $before = $this->auditSnapshot($cooperative);
            $cooperative->deleted_by = (string) $actor->getKey();
            $cooperative->save();
            $cooperative->delete();

            $this->record(
                actor: $actor,
                cooperative: $cooperative,
                eventKey: 'cooperatives.deleted',
                action: 'deleted',
                oldValues: $before,
                newValues: ['deleted' => true],
            );
        });
    }

    private function changeStatus(
        User $actor,
        Cooperative $cooperative,
        CooperativeStatus $status,
        string $eventKey,
        string $action,
    ): Cooperative {
        return DB::transaction(function () use (
            $actor, $cooperative, $status, $eventKey, $action,
        ): Cooperative {
            $cooperative = Cooperative::query()
                ->lockForUpdate()
                ->findOrFail($cooperative->getKey());

            Gate::forUser($actor)->authorize('update', $cooperative);

            if (! $actor->hasAnyRole(['super_admin_root', 'super_admin'])) {
                throw new AuthorizationException('Only super_admin_root or an assigned super_admin may change cooperative status.');
            }

            $before = $this->auditSnapshot($cooperative);

            if ($cooperative->status === $status) {
                return $cooperative;
            }

            $this->assertAllowedStatusTransition($actor, $cooperative->status, $status);

            $cooperative->status = $status;
            $cooperative->updated_by = (string) $actor->getKey();
            $cooperative->save();

            $this->record(
                actor: $actor,
                cooperative: $cooperative,
                eventKey: $eventKey,
                action: $action,
                oldValues: $before,
                newValues: $this->auditSnapshot($cooperative),
            );

            return $cooperative;
        });
    }

    private function assertAllowedStatusTransition(
        User $actor,
        CooperativeStatus $current,
        CooperativeStatus $target,
    ): void {
        $ordinaryTransitions = [
            CooperativeStatus::PENDING->value => [CooperativeStatus::ACTIVE, CooperativeStatus::ARCHIVED],
            CooperativeStatus::ACTIVE->value => [CooperativeStatus::INACTIVE, CooperativeStatus::SUSPENDED],
            CooperativeStatus::SUSPENDED->value => [CooperativeStatus::ACTIVE, CooperativeStatus::INACTIVE, CooperativeStatus::ARCHIVED],
            CooperativeStatus::INACTIVE->value => [CooperativeStatus::ACTIVE, CooperativeStatus::ARCHIVED],
            CooperativeStatus::ARCHIVED->value => [],
        ];

        $authorizedArchivedReactivation = $actor->hasAnyRole(['super_admin_root', 'super_admin'])
            && $current === CooperativeStatus::ARCHIVED
            && in_array($target, [CooperativeStatus::ACTIVE, CooperativeStatus::INACTIVE], true);

        if ($authorizedArchivedReactivation) {
            return;
        }

        if (! in_array($target, $ordinaryTransitions[$current->value] ?? [], true)) {
            throw new \DomainException(sprintf(
                'Transition from %s to %s is not allowed.',
                $current->value,
                $target->value,
            ));
        }
    }

    /** @param array<string, mixed> $attributes
     *  @return array<string, mixed>
     */
    private function filterWritableAttributes(array $attributes): array
    {
        $unknown = array_diff(array_keys($attributes), self::WRITABLE_FIELDS);

        if ($unknown !== []) {
            throw new \InvalidArgumentException(
                'Unsupported cooperative attributes: '.implode(', ', $unknown),
            );
        }

        return $attributes;
    }

    /** @return array<string, mixed> */
    private function auditSnapshot(Cooperative $cooperative): array
    {
        $snapshot = [];

        foreach (self::AUDITABLE_FIELDS as $field) {
            $value = $cooperative->getAttribute($field);
            $snapshot[$field] = $value instanceof \BackedEnum ? $value->value : $value;
        }

        return $snapshot;
    }

    /**
     * @param array<string, mixed>|null $oldValues
     * @param array<string, mixed>|null $newValues
     * @param array<string, mixed>|null $metadata
     */
    private function record(
        User $actor,
        Cooperative $cooperative,
        string $eventKey,
        string $action,
        ?array $oldValues = null,
        ?array $newValues = null,
        ?array $metadata = null,
    ): void {
        $this->audit->record(new AuditEntryDTO(
            eventKey: $eventKey,
            action: $action,
            actorType: User::class,
            actorId: (string) $actor->getKey(),
            cooperativeId: (string) $cooperative->getKey(),
            subjectType: Cooperative::class,
            subjectId: (string) $cooperative->getKey(),
            oldValues: $oldValues,
            newValues: $newValues,
            metadata: $metadata,
        ));
    }
}
