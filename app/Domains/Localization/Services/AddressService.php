<?php

declare(strict_types=1);

namespace App\Domains\Localization\Services;

use App\Core\Foundation\Services\BaseService;
use App\Domains\Audit\Services\AuditService;
use App\Domains\Cooperatives\Models\Cooperative;
use App\Domains\Countries\Models\Country;
use App\Domains\Identity\Users\Models\User;
use App\Domains\Localization\Enums\MoroccanRegion;
use App\Domains\Localization\Models\Address;
use App\Shared\DTOs\AuditEntryDTO;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use RuntimeException;

final class AddressService extends BaseService
{
    private const WRITABLE_FIELDS = ['number', 'street', 'floor', 'postal_code', 'city', 'region', 'latitude', 'longitude'];

    public function __construct(private readonly AuditService $audit) {}

    public function createForUser(User $actor, User $owner, array $attributes): Address
    {
        Gate::forUser($actor)->authorize('updateUserAddress', $owner);
        return DB::transaction(function () use ($actor, $owner, $attributes): Address {
            $locked = User::query()->lockForUpdate()->findOrFail($owner->getKey());
            Gate::forUser($actor)->authorize('updateUserAddress', $locked);
            $limit = $locked->hasRole('customer') ? 5 : 1;
            $count = Address::query()->where('user_id', $locked->getKey())->count();
            if ($count >= $limit) {
                throw ValidationException::withMessages(['address' => "La limite de {$limit} adresse(s) active(s) est atteinte."]);
            }
            $address = new Address($this->validatedAttributes($attributes));
            $address->user_id = $locked->getKey();
            $address->country_id = $this->moroccoCountry()->getKey();
            $address->is_primary = $count === 0;
            $address->created_by = (string) $actor->getKey();
            $address->save();
            $this->record($actor, $address, 'created', 'localization.address.created', newValues: $this->snapshot($address));
            return $address->load('country');
        });
    }

    public function updateForUser(User $actor, User $owner, array $attributes): Address
    {
        Gate::forUser($actor)->authorize('updateUserAddress', $owner);
        return DB::transaction(function () use ($actor, $owner, $attributes): Address {
            $locked = User::query()->lockForUpdate()->findOrFail($owner->getKey());
            Gate::forUser($actor)->authorize('updateUserAddress', $locked);
            $address = Address::query()->where('user_id', $locked->getKey())->where('is_primary', true)->lockForUpdate()->first()
                ?? Address::query()->where('user_id', $locked->getKey())->oldest()->lockForUpdate()->first();
            if ($address === null) {
                throw new RuntimeException('The user has no active address to update.');
            }
            return $this->updateAddress($actor, $address, $attributes);
        });
    }

    public function setPrimaryForUser(User $actor, User $owner, Address $address): Address
    {
        Gate::forUser($actor)->authorize('updateUserAddress', $owner);
        return DB::transaction(function () use ($actor, $owner, $address): Address {
            $lockedOwner = User::query()->whereKey($owner->getKey())->lockForUpdate()->firstOrFail();
            Gate::forUser($actor)->authorize('updateUserAddress', $lockedOwner);
            $target = Address::query()->whereKey($address->getKey())->where('user_id', $lockedOwner->getKey())->lockForUpdate()->firstOrFail();
            $previous = Address::query()->where('user_id', $owner->getKey())
                ->where('is_primary', true)->where('id', '<>', $target->getKey())
                ->lockForUpdate()->get();
            foreach ($previous as $oldPrimary) {
                $before = $this->snapshot($oldPrimary);
                $oldPrimary->forceFill(['is_primary' => false, 'updated_by' => (string) $actor->getKey()])->save();
                $this->record($actor, $oldPrimary, 'updated', 'localization.address.primary_changed',
                    oldValues: $before, newValues: $this->snapshot($oldPrimary));
            }
            $before = $this->snapshot($target);
            $target->forceFill(['is_primary' => true, 'updated_by' => (string) $actor->getKey()])->save();
            $this->record($actor, $target, 'updated', 'localization.address.primary_changed',
                oldValues: $before, newValues: $this->snapshot($target));
            return $target;
        });
    }

    public function deleteForUser(User $actor, User $owner, Address $address): void
    {
        Gate::forUser($actor)->authorize('updateUserAddress', $owner);
        DB::transaction(function () use ($actor, $owner, $address): void {
            $lockedOwner = User::query()->whereKey($owner->getKey())->lockForUpdate()->firstOrFail();
            Gate::forUser($actor)->authorize('updateUserAddress', $lockedOwner);
            $target = Address::query()->whereKey($address->getKey())->where('user_id', $lockedOwner->getKey())->lockForUpdate()->firstOrFail();
            $wasPrimary = $target->is_primary;
            $before = $this->snapshot($target);
            $target->forceFill(['is_primary' => false, 'deletion_source' => 'voluntary', 'deleted_by' => (string) $actor->getKey()])->save();
            $target->delete();
            if ($wasPrimary) {
                $next = Address::query()->where('user_id', $owner->getKey())
                    ->orderBy('created_at')->orderBy('id')->lockForUpdate()->first();

                if ($next !== null) {
                    $nextBefore = $this->snapshot($next);
                    $next->forceFill([
                        'is_primary' => true,
                        'updated_by' => (string) $actor->getKey(),
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
            $this->record($actor, $target, 'deleted', 'localization.address.deleted', oldValues: $before,
                newValues: ['deleted' => true, 'deletion_source' => 'voluntary']);
        });
    }

    public function createForCooperative(User $actor, Cooperative $cooperative, array $attributes): Address
    {
        Gate::forUser($actor)->authorize('createCooperativeAddress', $cooperative);
        return DB::transaction(function () use ($actor, $cooperative, $attributes): Address {
            $locked = Cooperative::query()->lockForUpdate()->findOrFail($cooperative->getKey());
            Gate::forUser($actor)->authorize('createCooperativeAddress', $cooperative);
            if (Address::query()->where('cooperative_id', $locked->getKey())->exists()) {
                throw ValidationException::withMessages(['address' => 'Cette coopérative possède déjà une adresse active.']);
            }
            $address = new Address($this->validatedAttributes($attributes));
            $address->cooperative_id = $locked->getKey();
            $address->country_id = $this->moroccoCountry()->getKey();
            $address->is_primary = true;
            $address->created_by = (string) $actor->getKey();
            $address->save();
            $this->record($actor, $address, 'created', 'localization.address.created',
                cooperativeId: (string) $locked->getKey(), newValues: $this->snapshot($address));
            return $address->load('country');
        });
    }

    public function updateForCooperative(User $actor, Cooperative $cooperative, array $attributes): Address
    {
        Gate::forUser($actor)->authorize('updateCooperativeAddress', $cooperative);
        return DB::transaction(function () use ($actor, $cooperative, $attributes): Address {
            $locked = Cooperative::query()->lockForUpdate()->findOrFail($cooperative->getKey());
            Gate::forUser($actor)->authorize('updateCooperativeAddress', $locked);
            $address = Address::query()->where('cooperative_id', $locked->getKey())->lockForUpdate()->first();
            return $address === null
                ? $this->createForCooperative($actor, $locked, $attributes)
                : $this->updateAddress($actor, $address, $attributes);
        });
    }

    public function softDeleteUserAddresses(User $owner, string $actorId): string
    {
        return $this->softDeleteOwnerAddresses('user_id', (string) $owner->getKey(), $actorId);
    }

    public function softDeleteCooperativeAddresses(Cooperative $owner, string $actorId): string
    {
        return $this->softDeleteOwnerAddresses('cooperative_id', (string) $owner->getKey(), $actorId);
    }

    private function softDeleteOwnerAddresses(string $ownerColumn, string $ownerId, string $actorId): string
    {
        $batch = (string) Str::ulid();
        $rows = Address::query()->where($ownerColumn, $ownerId)->lockForUpdate()->get();
        $actor = User::query()->find($actorId);
        foreach ($rows as $address) {
            $before = $this->snapshot($address);
            $address->forceFill([
                'is_primary' => false, 'deletion_source' => 'owner',
                'owner_deletion_batch_id' => $batch, 'deleted_by' => $actorId,
            ])->save();
            $address->delete();
            if ($actor !== null) {
                $this->record($actor, $address, 'deleted', 'localization.address.owner_deleted',
                    oldValues: $before, newValues: ['deleted' => true, 'deletion_source' => 'owner']);
            }
        }
        return $batch;
    }

    public function restoreLatestCooperativeAddresses(Cooperative $owner, string $actorId): void
    {
        DB::transaction(function () use ($owner, $actorId): void {
            Cooperative::query()->whereKey($owner->getKey())->lockForUpdate()->firstOrFail();

            $batch = Address::withTrashed()->where('cooperative_id', $owner->getKey())
                ->where('deletion_source', 'owner')->whereNotNull('owner_deletion_batch_id')
                ->orderByDesc('deleted_at')->value('owner_deletion_batch_id');

            if ($batch === null) {
                return;
            }

            $rows = Address::withTrashed()->where('cooperative_id', $owner->getKey())
                ->where('deletion_source', 'owner')->where('owner_deletion_batch_id', $batch)
                ->orderBy('created_at')->lockForUpdate()->get();

            $active = Address::query()->where('cooperative_id', $owner->getKey())->count();
            if ($active + $rows->count() > 1) {
                throw ValidationException::withMessages([
                    'address' => 'La restauration dépasserait la limite d’une adresse active pour cette coopérative.',
                ]);
            }

            $actor = User::query()->find($actorId);
            foreach ($rows as $address) {
                $address->restore();
                $address->forceFill([
                    'deletion_source' => null,
                    'owner_deletion_batch_id' => null,
                    'is_primary' => $active === 0,
                    'updated_by' => $actorId,
                ])->save();

                if ($actor !== null) {
                    $this->record(
                        $actor,
                        $address,
                        'restored',
                        'localization.address.owner_restored',
                        oldValues: ['deleted' => true, 'deletion_source' => 'owner'],
                        newValues: $this->snapshot($address),
                    );
                }
            }
        });
    }

    public function restoreLatestUserAddresses(User $owner, string $actorId): void
    {
        $batch = Address::withTrashed()->where('user_id', $owner->getKey())
            ->where('deletion_source', 'owner')->whereNotNull('owner_deletion_batch_id')
            ->orderByDesc('deleted_at')->value('owner_deletion_batch_id');
        if ($batch !== null) {
            $this->restoreUserAddresses($owner, (string) $batch, $actorId);
        }
    }

    public function restoreUserAddresses(User $owner, string $batch, string $actorId): void
    {
        DB::transaction(function () use ($owner, $batch, $actorId): void {
            User::query()->whereKey($owner->getKey())->lockForUpdate()->firstOrFail();
            $rows = Address::withTrashed()->where('user_id', $owner->getKey())
                ->where('deletion_source', 'owner')->where('owner_deletion_batch_id', $batch)
                ->orderBy('created_at')->lockForUpdate()->get();
            $limit = $owner->hasRole('customer') ? 5 : 1;
            $active = Address::query()->where('user_id', $owner->getKey())->count();
            if ($active + $rows->count() > $limit) {
                throw ValidationException::withMessages(['address' => 'La restauration dépasserait la limite d’adresses actives.']);
            }
            $actor = User::query()->find($actorId);
            foreach ($rows as $index => $address) {
                $address->restore();
                $address->forceFill([
                    'deletion_source' => null, 'owner_deletion_batch_id' => null,
                    'is_primary' => $active === 0 && $index === 0, 'updated_by' => $actorId,
                ])->save();
                if ($actor !== null) {
                    $this->record($actor, $address, 'restored', 'localization.address.owner_restored',
                        oldValues: ['deleted' => true, 'deletion_source' => 'owner'],
                        newValues: $this->snapshot($address));
                }
            }
        });
    }

    private function updateAddress(User $actor, Address $address, array $attributes): Address
    {
        $before = $this->snapshot($address);
        $address->fill($this->validatedAttributes($attributes));
        if (! $address->isDirty()) return $address;
        $address->updated_by = (string) $actor->getKey();
        $address->save();
        $this->record($actor, $address, 'updated', 'localization.address.updated',
            cooperativeId: $address->cooperative_id, oldValues: $before, newValues: $this->snapshot($address));
        return $address->load('country');
    }

    private function validatedAttributes(array $attributes): array
    {
        $unknown = array_diff(array_keys($attributes), self::WRITABLE_FIELDS);
        if ($unknown !== []) throw new InvalidArgumentException('Unsupported address attributes: '.implode(', ', $unknown));
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
            if (isset($data[$field])) $data[$field] = trim($data[$field]);
        }
        return $data;
    }

    private function moroccoCountry(): Country
    {
        $country = Country::query()->where('code', 'MA')->where('is_active', true)->first();
        if ($country === null) {
            throw ValidationException::withMessages(['country' => 'Le Maroc doit être configuré et actif pour enregistrer une adresse en V1.']);
        }
        return $country;
    }

    private function snapshot(Address $address): array
    {
        return [
            'number' => $address->number, 'street' => $address->street, 'floor' => $address->floor,
            'postal_code' => $address->postal_code, 'city' => $address->city, 'region' => $address->region,
            'country_code' => $address->country?->code, 'latitude' => $address->latitude,
            'longitude' => $address->longitude, 'user_id' => $address->user_id,
            'cooperative_id' => $address->cooperative_id, 'is_primary' => $address->is_primary,
        ];
    }

    private function record(
        User $actor, Address $address, string $action, string $eventKey,
        ?string $cooperativeId = null, ?array $oldValues = null, ?array $newValues = null,
    ): void {
        $coopId = $cooperativeId ?? $address->cooperative_id;
        if ($coopId === null && $address->user_id !== null) {
            $coopId = User::query()->whereKey($address->user_id)->value('cooperative_id');
        }
        $this->audit->record(new AuditEntryDTO(
            eventKey: $eventKey, action: $action, actorType: User::class,
            actorId: (string) $actor->getKey(),
            cooperativeId: $coopId === null ? null : (string) $coopId,
            subjectType: Address::class, subjectId: (string) $address->getKey(),
            oldValues: $oldValues, newValues: $newValues,
        ));
    }
}
