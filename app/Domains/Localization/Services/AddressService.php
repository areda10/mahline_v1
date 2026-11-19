<?php

declare(strict_types=1);

namespace App\Domains\Localization\Services;

use App\Core\Foundation\Services\BaseService;
use App\Domains\Audit\Contracts\AuditWriter;
use App\Domains\Localization\Contracts\CooperativeAddressManager;
use App\Domains\Localization\Contracts\CooperativeAddressOwnerGateway;
use App\Domains\Localization\Contracts\UserAddressLifecycleManager;
use App\Domains\Localization\Contracts\UserAddressOwnerGateway;
use App\Domains\Localization\Enums\MoroccanRegion;
use App\Domains\Localization\Models\Address;
use App\Domains\Localization\Models\Country;
use App\Shared\Contracts\ActorContextProvider;
use App\Shared\DTOs\ActorContext;
use App\Shared\DTOs\AuditEntryDTO;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use RuntimeException;

final class AddressService extends BaseService implements CooperativeAddressManager, UserAddressLifecycleManager
{
    private const WRITABLE_FIELDS = [
        'number', 'street', 'floor', 'postal_code', 'city', 'region', 'latitude', 'longitude',
    ];

    public function __construct(
        private readonly AuditWriter $audit,
        private readonly ActorContextProvider $actors,
        private readonly UserAddressOwnerGateway $userOwners,
        private readonly CooperativeAddressOwnerGateway $cooperativeOwners,
    ) {}

    /** @param array<string, mixed> $attributes */
    public function savePrimaryAddressForUser(string $actorId, string $ownerId, array $attributes): void
    {
        $actor = $this->actors->forUserId($actorId);

        DB::transaction(function () use ($actor, $actorId, $ownerId, $attributes): void {
            $owner = $this->userOwners->lock($ownerId);
            $this->userOwners->authorize($actorId, 'updateUserAddress', $ownerId);

            $address = Address::query()
                ->where('user_id', $owner->id)
                ->where('is_primary', true)
                ->lockForUpdate()
                ->first()
                ?? Address::query()
                    ->where('user_id', $owner->id)
                    ->oldest()
                    ->lockForUpdate()
                    ->first();

            if ($address !== null) {
                $this->updateAddress($actor, $address, $attributes);

                return;
            }

            $address = new Address($this->validatedAttributes($attributes));
            $address->user_id = $owner->id;
            $address->country_id = $this->moroccoCountry()->getKey();
            $address->is_primary = true;
            $address->created_by = $actorId;
            $address->save();

            $this->record(
                $actor,
                $address,
                'created',
                'localization.address.created',
                newValues: $this->snapshot($address),
            );
        });
    }

    /** @param array<string, mixed> $attributes */
    public function createForUser(string $actorId, string $ownerId, array $attributes): Address
    {
        $actor = $this->actors->forUserId($actorId);

        return DB::transaction(function () use ($actor, $actorId, $ownerId, $attributes): Address {
            $owner = $this->userOwners->lock($ownerId);
            $this->userOwners->authorize($actorId, 'updateUserAddress', $ownerId);

            $limit = $owner->isCustomer ? 5 : 1;
            $count = Address::query()->where('user_id', $owner->id)->count();

            if ($count >= $limit) {
                throw ValidationException::withMessages([
                    'address' => "La limite de {$limit} adresse(s) active(s) est atteinte.",
                ]);
            }

            $address = new Address($this->validatedAttributes($attributes));
            $address->user_id = $owner->id;
            $address->country_id = $this->moroccoCountry()->getKey();
            $address->is_primary = $count === 0;
            $address->created_by = $actorId;
            $address->save();

            $this->record(
                $actor,
                $address,
                'created',
                'localization.address.created',
                newValues: $this->snapshot($address),
            );

            return $address->load('country');
        });
    }

    /** @param array<string, mixed> $attributes */
    public function updateForUser(string $actorId, string $ownerId, array $attributes): Address
    {
        $actor = $this->actors->forUserId($actorId);

        return DB::transaction(function () use ($actor, $actorId, $ownerId, $attributes): Address {
            $owner = $this->userOwners->lock($ownerId);
            $this->userOwners->authorize($actorId, 'updateUserAddress', $ownerId);

            $address = Address::query()
                ->where('user_id', $owner->id)
                ->where('is_primary', true)
                ->lockForUpdate()
                ->first()
                ?? Address::query()
                    ->where('user_id', $owner->id)
                    ->oldest()
                    ->lockForUpdate()
                    ->first();

            if ($address === null) {
                throw new RuntimeException('The user has no active address to update.');
            }

            return $this->updateAddress($actor, $address, $attributes);
        });
    }

    public function setPrimaryForUser(string $actorId, string $ownerId, string $addressId): Address
    {
        $actor = $this->actors->forUserId($actorId);

        return DB::transaction(function () use ($actor, $actorId, $ownerId, $addressId): Address {
            $owner = $this->userOwners->lock($ownerId);
            $this->userOwners->authorize($actorId, 'updateUserAddress', $ownerId);

            $target = Address::query()
                ->whereKey($addressId)
                ->where('user_id', $owner->id)
                ->lockForUpdate()
                ->firstOrFail();

            $previous = Address::query()
                ->where('user_id', $owner->id)
                ->where('is_primary', true)
                ->where('id', '<>', $target->getKey())
                ->lockForUpdate()
                ->get();

            foreach ($previous as $oldPrimary) {
                $before = $this->snapshot($oldPrimary);
                $oldPrimary->forceFill([
                    'is_primary' => false,
                    'updated_by' => $actorId,
                ])->save();

                $this->record(
                    $actor,
                    $oldPrimary,
                    'updated',
                    'localization.address.primary_changed',
                    oldValues: $before,
                    newValues: $this->snapshot($oldPrimary),
                );
            }

            $before = $this->snapshot($target);
            $target->forceFill([
                'is_primary' => true,
                'updated_by' => $actorId,
            ])->save();

            $this->record(
                $actor,
                $target,
                'updated',
                'localization.address.primary_changed',
                oldValues: $before,
                newValues: $this->snapshot($target),
            );

            return $target;
        });
    }

    public function deleteForUser(string $actorId, string $ownerId, string $addressId): void
    {
        $actor = $this->actors->forUserId($actorId);

        DB::transaction(function () use ($actor, $actorId, $ownerId, $addressId): void {
            $owner = $this->userOwners->lock($ownerId);
            $this->userOwners->authorize($actorId, 'updateUserAddress', $ownerId);

            $target = Address::query()
                ->whereKey($addressId)
                ->where('user_id', $owner->id)
                ->lockForUpdate()
                ->firstOrFail();

            $wasPrimary = $target->is_primary;
            $before = $this->snapshot($target);
            $target->forceFill([
                'is_primary' => false,
                'deletion_source' => 'voluntary',
                'deleted_by' => $actorId,
            ])->save();
            $target->delete();

            if ($wasPrimary) {
                $next = Address::query()
                    ->where('user_id', $owner->id)
                    ->orderBy('created_at')
                    ->orderBy('id')
                    ->lockForUpdate()
                    ->first();

                if ($next !== null) {
                    $nextBefore = $this->snapshot($next);
                    $next->forceFill([
                        'is_primary' => true,
                        'updated_by' => $actorId,
                    ])->save();

                    $this->record(
                        $actor,
                        $next,
                        'updated',
                        'localization.address.primary_promoted',
                        oldValues: $nextBefore,
                        newValues: $this->snapshot($next),
                    );
                }
            }

            $this->record(
                $actor,
                $target,
                'deleted',
                'localization.address.deleted',
                oldValues: $before,
                newValues: ['deleted' => true, 'deletion_source' => 'voluntary'],
            );
        });
    }

    /** @param array<string, mixed> $attributes */
    public function createAddressForCooperative(string $actorId, string $cooperativeId, array $attributes): void
    {
        $this->createForCooperative($actorId, $cooperativeId, $attributes);
    }

    /** @param array<string, mixed> $attributes */
    public function updateAddressForCooperative(string $actorId, string $cooperativeId, array $attributes): void
    {
        $this->updateForCooperative($actorId, $cooperativeId, $attributes);
    }

    public function softDeleteUserAddressesById(string $userId, string $actorId): string
    {
        return $this->softDeleteOwnerAddresses('user_id', $userId, $actorId);
    }

    public function restoreLatestUserAddressesById(string $userId, string $actorId): void
    {
        $batch = Address::withTrashed()
            ->where('user_id', $userId)
            ->where('deletion_source', 'owner')
            ->whereNotNull('owner_deletion_batch_id')
            ->orderByDesc('deleted_at')
            ->value('owner_deletion_batch_id');

        if ($batch !== null) {
            $this->restoreUserAddresses($userId, (string) $batch, $actorId);
        }
    }

    public function softDeleteCooperativeAddressesById(string $cooperativeId, string $actorId): string
    {
        return $this->softDeleteOwnerAddresses('cooperative_id', $cooperativeId, $actorId);
    }

    public function restoreLatestCooperativeAddressesById(string $cooperativeId, string $actorId): void
    {
        $actor = $this->actors->forUserId($actorId);

        DB::transaction(function () use ($actor, $cooperativeId, $actorId): void {
            $owner = $this->cooperativeOwners->lock($cooperativeId);
            $batch = Address::withTrashed()
                ->where('cooperative_id', $owner->id)
                ->where('deletion_source', 'owner')
                ->whereNotNull('owner_deletion_batch_id')
                ->orderByDesc('deleted_at')
                ->value('owner_deletion_batch_id');

            if ($batch === null) {
                return;
            }

            $rows = Address::withTrashed()
                ->where('cooperative_id', $owner->id)
                ->where('deletion_source', 'owner')
                ->where('owner_deletion_batch_id', $batch)
                ->orderBy('created_at')
                ->lockForUpdate()
                ->get();

            $active = Address::query()->where('cooperative_id', $owner->id)->count();

            if ($active + $rows->count() > 1) {
                throw ValidationException::withMessages([
                    'address' => 'La restauration dépasserait la limite d’une adresse active pour cette coopérative.',
                ]);
            }

            foreach ($rows as $address) {
                $address->restore();
                $address->forceFill([
                    'deletion_source' => null,
                    'owner_deletion_batch_id' => null,
                    'is_primary' => $active === 0,
                    'updated_by' => $actorId,
                ])->save();

                $this->record(
                    $actor,
                    $address,
                    'restored',
                    'localization.address.owner_restored',
                    oldValues: ['deleted' => true, 'deletion_source' => 'owner'],
                    newValues: $this->snapshot($address),
                );
            }
        });
    }

    /** @param array<string, mixed> $attributes */
    public function createForCooperative(string $actorId, string $cooperativeId, array $attributes): Address
    {
        $actor = $this->actors->forUserId($actorId);

        return DB::transaction(function () use ($actor, $actorId, $cooperativeId, $attributes): Address {
            $owner = $this->cooperativeOwners->lock($cooperativeId);
            $this->cooperativeOwners->authorize($actorId, 'createCooperativeAddress', $owner->id);

            return $this->persistCooperativeAddress($actor, $owner->id, $attributes);
        });
    }

    /** @param array<string, mixed> $attributes */
    public function updateForCooperative(string $actorId, string $cooperativeId, array $attributes): Address
    {
        $actor = $this->actors->forUserId($actorId);

        return DB::transaction(function () use ($actor, $actorId, $cooperativeId, $attributes): Address {
            $owner = $this->cooperativeOwners->lock($cooperativeId);
            $this->cooperativeOwners->authorize($actorId, 'updateCooperativeAddress', $owner->id);
            $address = Address::query()
                ->where('cooperative_id', $owner->id)
                ->lockForUpdate()
                ->first();

            return $address === null
                ? $this->persistCooperativeAddress($actor, $owner->id, $attributes)
                : $this->updateAddress($actor, $address, $attributes);
        });
    }

    private function softDeleteOwnerAddresses(string $ownerColumn, string $ownerId, string $actorId): string
    {
        $actor = $this->actors->forUserId($actorId);

        return DB::transaction(function () use ($ownerColumn, $ownerId, $actorId, $actor): string {
            match ($ownerColumn) {
                'user_id' => $this->userOwners->lock($ownerId),
                'cooperative_id' => $this->cooperativeOwners->lock($ownerId),
                default => throw new InvalidArgumentException('Unsupported address owner column.'),
            };

            $batch = (string) Str::ulid();
            $rows = Address::query()
                ->where($ownerColumn, $ownerId)
                ->lockForUpdate()
                ->get();

            foreach ($rows as $address) {
                $before = $this->snapshot($address);
                $address->forceFill([
                    'is_primary' => false,
                    'deletion_source' => 'owner',
                    'owner_deletion_batch_id' => $batch,
                    'deleted_by' => $actorId,
                ])->save();
                $address->delete();

                if ($actor->authorized) {
                    $this->record(
                        $actor,
                        $address,
                        'deleted',
                        'localization.address.owner_deleted',
                        oldValues: $before,
                        newValues: ['deleted' => true, 'deletion_source' => 'owner'],
                    );
                }
            }

            return $batch;
        });
    }

    private function restoreUserAddresses(string $userId, string $batch, string $actorId): void
    {
        $actor = $this->actors->forUserId($actorId);

        DB::transaction(function () use ($userId, $batch, $actorId, $actor): void {
            $owner = $this->userOwners->lock($userId);
            $rows = Address::withTrashed()
                ->where('user_id', $owner->id)
                ->where('deletion_source', 'owner')
                ->where('owner_deletion_batch_id', $batch)
                ->orderBy('created_at')
                ->lockForUpdate()
                ->get();

            $limit = $owner->isCustomer ? 5 : 1;
            $active = Address::query()->where('user_id', $owner->id)->count();

            if ($active + $rows->count() > $limit) {
                throw ValidationException::withMessages([
                    'address' => 'La restauration dépasserait la limite d’adresses actives.',
                ]);
            }

            foreach ($rows as $index => $address) {
                $address->restore();
                $address->forceFill([
                    'deletion_source' => null,
                    'owner_deletion_batch_id' => null,
                    'is_primary' => $active === 0 && $index === 0,
                    'updated_by' => $actorId,
                ])->save();

                $this->record(
                    $actor,
                    $address,
                    'restored',
                    'localization.address.owner_restored',
                    oldValues: ['deleted' => true, 'deletion_source' => 'owner'],
                    newValues: $this->snapshot($address),
                );
            }
        });
    }

    private function persistCooperativeAddress(ActorContext $actor, string $cooperativeId, array $attributes): Address
    {
        if (Address::query()->where('cooperative_id', $cooperativeId)->exists()) {
            throw ValidationException::withMessages([
                'address' => 'Cette coopérative possède déjà une adresse active.',
            ]);
        }

        $address = new Address($this->validatedAttributes($attributes));
        $address->cooperative_id = $cooperativeId;
        $address->country_id = $this->moroccoCountry()->getKey();
        $address->is_primary = true;
        $address->created_by = $actor->id;
        $address->save();

        $this->record(
            $actor,
            $address,
            'created',
            'localization.address.created',
            cooperativeId: $cooperativeId,
            newValues: $this->snapshot($address),
        );

        return $address->load('country');
    }

    private function updateAddress(ActorContext $actor, Address $address, array $attributes): Address
    {
        $before = $this->snapshot($address);
        $address->fill($this->validatedAttributes($attributes));

        if (! $address->isDirty()) {
            return $address;
        }

        $address->updated_by = $actor->id;
        $address->save();

        $this->record(
            $actor,
            $address,
            'updated',
            'localization.address.updated',
            cooperativeId: $address->cooperative_id,
            oldValues: $before,
            newValues: $this->snapshot($address),
        );

        return $address->load('country');
    }

    /** @param array<string, mixed> $attributes
     * @return array<string, mixed>
     */
    private function validatedAttributes(array $attributes): array
    {
        $unknown = array_diff(array_keys($attributes), self::WRITABLE_FIELDS);

        if ($unknown !== []) {
            throw new InvalidArgumentException('Unsupported address attributes: '.implode(', ', $unknown));
        }

        $data = Validator::make($attributes, [
            'number' => ['sometimes', 'nullable', 'string', 'max:50'],
            'street' => ['required', 'string', 'max:255'],
            'floor' => ['sometimes', 'nullable', 'string', 'max:50'],
            'postal_code' => ['sometimes', 'nullable', 'string', 'max:20'],
            'city' => ['required', 'string', 'max:120'],
            'region' => ['sometimes', 'nullable', 'string', 'max:120', Rule::in(MoroccanRegion::values())],
            'latitude' => ['sometimes', 'nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['sometimes', 'nullable', 'numeric', 'between:-180,180'],
        ])->validate();

        foreach (['number', 'street', 'floor', 'postal_code', 'city', 'region'] as $field) {
            if (isset($data[$field])) {
                $data[$field] = trim($data[$field]);
            }
        }

        return $data;
    }

    private function moroccoCountry(): Country
    {
        $country = Country::query()->where('code', 'MA')->where('is_active', true)->first();

        if ($country === null) {
            throw ValidationException::withMessages([
                'country' => 'Le Maroc doit être configuré et actif pour enregistrer une adresse en V1.',
            ]);
        }

        return $country;
    }

    /** @return array<string, mixed> */
    private function snapshot(Address $address): array
    {
        return [
            'number' => $address->number,
            'street' => $address->street,
            'floor' => $address->floor,
            'postal_code' => $address->postal_code,
            'city' => $address->city,
            'region' => $address->region,
            'country_code' => $address->country?->code,
            'latitude' => $address->latitude,
            'longitude' => $address->longitude,
            'user_id' => $address->user_id,
            'cooperative_id' => $address->cooperative_id,
            'is_primary' => $address->is_primary,
        ];
    }

    /**
     * @param array<string, mixed>|null $oldValues
     * @param array<string, mixed>|null $newValues
     */
    private function record(
        ActorContext $actor,
        Address $address,
        string $action,
        string $eventKey,
        ?string $cooperativeId = null,
        ?array $oldValues = null,
        ?array $newValues = null,
    ): void {
        $coopId = $cooperativeId ?? $address->cooperative_id;
        $coopId = $coopId === null ? null : (string) $coopId;

        if ($coopId === null && $address->user_id !== null) {
            $coopId = $this->userOwners->cooperativeId((string) $address->user_id);
        }

        $this->audit->record(new AuditEntryDTO(
            eventKey: $eventKey,
            action: $action,
            actorType: $actor->actorType,
            actorId: $actor->id,
            cooperativeId: $coopId,
            subjectType: Address::class,
            subjectId: (string) $address->getKey(),
            oldValues: $oldValues,
            newValues: $newValues,
        ));
    }
}
