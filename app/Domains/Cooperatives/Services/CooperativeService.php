<?php

declare(strict_types=1);

namespace App\Domains\Cooperatives\Services;

use App\Domains\Audit\Contracts\AuditWriter;
use App\Domains\Cooperatives\Enums\CooperativeStatus;
use App\Domains\Cooperatives\Contracts\CooperativeMemberAccessRevoker;
use App\Domains\Cooperatives\Events\CooperativeCreatedV1;
use App\Domains\Cooperatives\Models\Cooperative;
use App\Shared\Contracts\ActorAuthorizer;
use App\Shared\Contracts\ActorContextProvider;
use App\Shared\DTOs\ActorContext;
use App\Domains\Localization\Contracts\CooperativeAddressManager;
use App\Shared\DTOs\AuditEntryDTO;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;

final class CooperativeService
{
    /** @var list<string> */
    private const WRITABLE_FIELDS = [
        'name', 'rib', 'tax_id', 'phone', 'email', 'rc', 'ice',
    ];

    /** @var list<string> */
    private const COOPERATIVE_ADMIN_WRITABLE_FIELDS = ['phone', 'email'];

    /** @var list<string> */
    private const AUDITABLE_FIELDS = [
        'name', 'phone', 'email', 'rc', 'status',
    ];

    public function __construct(
        private readonly AuditWriter $audit,
        private readonly CooperativeAddressManager $cooperativeAddresses,
        private readonly ActorContextProvider $actors,
        private readonly ActorAuthorizer $authorizer,
        private readonly CooperativeMemberAccessRevoker $memberAccessRevoker,
    ) {}

    /** @param array<string, mixed> $attributes */
    public function create(string $actorId, array $attributes, array $addressAttributes): Cooperative
    {
        $actor = $this->actors->forUserId($actorId);
        $this->authorizer->authorize($actor->id, 'create', Cooperative::class);
        $attributes = $this->filterCreateAttributes($attributes);

        return DB::transaction(function () use ($actor, $attributes, $addressAttributes): Cooperative {
            $cooperative = new Cooperative;
            $cooperative->fill($attributes);
            $cooperative->status ??= CooperativeStatus::PENDING;
            $cooperative->created_by = (string) $actor->id;
            $cooperative->save();

            // Identity consumes this versioned event and owns its assignment pivot.
            if ($actor->hasRole('super_admin') && ! $actor->hasRole('super_admin_root')) {
                Event::dispatch(new CooperativeCreatedV1(
                    cooperativeId: (string) $cooperative->getKey(),
                    creatorUserId: (string) $actor->id,
                ));
            }

            // The cooperative and its unique address are created atomically.
            // The super_admin assignment is established first so address authorization
            // and audit context observe the final cooperative ownership scope.
            $this->cooperativeAddresses->createAddressForCooperative($actor->id, (string) $cooperative->getKey(), $addressAttributes);

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
        string $actorId,
        Cooperative $cooperative,
        array $attributes,
    ): Cooperative {
        $attributes = $this->filterWritableAttributes($attributes);
        $actor = $this->actors->forUserId($actorId);

        return DB::transaction(function () use (
            $actor,
            $cooperative,
            $attributes,
        ): Cooperative {
            $cooperative = Cooperative::query()
                ->lockForUpdate()
                ->findOrFail($cooperative->getKey());

            $this->authorizer->authorize($actor->id, 'update', $cooperative);

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
            $cooperative->updated_by = (string) $actor->id;
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

    public function activate(string $actorId, Cooperative $cooperative): Cooperative
    {
        return $this->changeStatus($actorId, $cooperative, CooperativeStatus::ACTIVE,
            'cooperatives.activated', 'activated');
    }

    public function deactivate(string $actorId, Cooperative $cooperative): Cooperative
    {
        return $this->changeStatus($actorId, $cooperative, CooperativeStatus::INACTIVE,
            'cooperatives.deactivated', 'deactivated');
    }

    public function suspend(string $actorId, Cooperative $cooperative): Cooperative
    {
        return $this->changeStatus($actorId, $cooperative, CooperativeStatus::SUSPENDED,
            'cooperatives.suspended', 'suspended');
    }

    public function archive(string $actorId, Cooperative $cooperative): Cooperative
    {
        return $this->changeStatus($actorId, $cooperative, CooperativeStatus::ARCHIVED,
            'cooperatives.archived', 'archived');
    }

    public function delete(string $actorId, Cooperative $cooperative): void
    {
        $actor = $this->actors->forUserId($actorId);

        DB::transaction(function () use ($actor, $cooperative): void {
            $cooperative = Cooperative::withTrashed()
                ->lockForUpdate()
                ->findOrFail($cooperative->getKey());

            $this->authorizer->authorize($actor->id, 'delete', $cooperative);

            if ($cooperative->trashed()) {
                return;
            }

            $before = $this->auditSnapshot($cooperative);
            $accessRevocation = $this->memberAccessRevoker->revokeMemberAccess(
                (string) $cooperative->getKey(),
                'cooperative_deleted',
            );
            $this->cooperativeAddresses->softDeleteCooperativeAddressesById((string) $cooperative->getKey(), $actor->id);
            $cooperative->deleted_by = (string) $actor->id;
            $cooperative->save();
            $cooperative->delete();

            $this->record(
                actor: $actor,
                cooperative: $cooperative,
                eventKey: 'cooperatives.deleted',
                action: 'deleted',
                oldValues: $before,
                newValues: ['deleted' => true],
                metadata: ['access_revocation' => $accessRevocation],
            );
        });
    }

    public function restore(string $actorId, Cooperative $cooperative): Cooperative
    {
        $actor = $this->actors->forUserId($actorId);
        if (! $actor->hasRole('super_admin_root') || ! $actor->authorized) {
            throw new AuthorizationException('Only super_admin_root may restore a deleted cooperative.');
        }

        return DB::transaction(function () use ($actor, $cooperative): Cooperative {
            $locked = Cooperative::withTrashed()
                ->whereKey($cooperative->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $this->authorizer->authorize($actor->id, 'delete', $locked);

            if (! $locked->trashed()) {
                return $locked;
            }

            $oldDeletedAt = $locked->deleted_at?->toISOString();
            $locked->restore();
            $this->cooperativeAddresses->restoreLatestCooperativeAddressesById((string) $locked->getKey(), $actor->id);

            $this->record(
                actor: $actor,
                cooperative: $locked,
                eventKey: 'cooperatives.restored',
                action: 'restored',
                oldValues: ['deleted_at' => $oldDeletedAt],
                newValues: ['deleted_at' => null],
            );

            return $locked->fresh();
        });
    }

    private function changeStatus(
        string $actorId,
        Cooperative $cooperative,
        CooperativeStatus $status,
        string $eventKey,
        string $action,
    ): Cooperative {
        $actor = $this->actors->forUserId($actorId);

        return DB::transaction(function () use (
            $actor, $cooperative, $status, $eventKey, $action,
        ): Cooperative {
            $cooperative = Cooperative::query()
                ->lockForUpdate()
                ->findOrFail($cooperative->getKey());

            $this->authorizer->authorize($actor->id, 'update', $cooperative);

            if (! $actor->hasAnyRole(['super_admin_root', 'super_admin'])) {
                throw new AuthorizationException('Only super_admin_root or an assigned super_admin may change cooperative status.');
            }

            $before = $this->auditSnapshot($cooperative);

            if ($cooperative->status === $status) {
                return $cooperative;
            }

            $previousStatus = $cooperative->status;
            $this->assertAllowedStatusTransition($actor, $previousStatus, $status);

            $cooperative->status = $status;
            $cooperative->updated_by = (string) $actor->id;
            $cooperative->save();

            $accessRevocation = null;
            if ($status !== CooperativeStatus::ACTIVE) {
                $accessRevocation = $this->memberAccessRevoker->revokeMemberAccess(
                    (string) $cooperative->getKey(),
                    'cooperative_'.$status->value,
                );
            }

            $this->record(
                actor: $actor,
                cooperative: $cooperative,
                eventKey: $eventKey,
                action: $action,
                oldValues: $before,
                newValues: $this->auditSnapshot($cooperative),
                metadata: $accessRevocation === null ? null : ['access_revocation' => $accessRevocation],
            );

            return $cooperative;
        });
    }

    private function assertAllowedStatusTransition(
        ActorContext $actor,
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
     * @return array<string, mixed>
     */
    private function filterCreateAttributes(array $attributes): array
    {
        if (array_key_exists('address_id', $attributes)) {
            throw new \InvalidArgumentException('A cooperative address must be created through the Localization address contract.');
        }

        return $this->filterWritableAttributes($attributes);
    }

    /** @param array<string, mixed> $attributes
     * @return array<string, mixed>
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
     * @param  array<string, mixed>|null  $oldValues
     * @param  array<string, mixed>|null  $newValues
     * @param  array<string, mixed>|null  $metadata
     */
    private function record(
        ActorContext $actor,
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
            actorType: $actor->actorType,
            actorId: (string) $actor->id,
            cooperativeId: (string) $cooperative->getKey(),
            subjectType: Cooperative::class,
            subjectId: (string) $cooperative->getKey(),
            oldValues: $oldValues,
            newValues: $newValues,
            metadata: $metadata,
        ));
    }
}
