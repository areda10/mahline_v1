<?php

declare(strict_types=1);

namespace App\Domains\Localization\Services;

use App\Core\Foundation\Services\BaseService;
use App\Domains\Audit\Services\AuditService;
use App\Domains\Cooperatives\Models\Cooperative;
use App\Domains\Countries\Models\Country;
use App\Domains\Identity\Users\Models\User;
use App\Domains\Localization\Models\Address;
use App\Shared\DTOs\AuditEntryDTO;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use RuntimeException;

final class AddressService extends BaseService
{
    private const WRITABLE_FIELDS = [
        'number',
        'street',
        'floor',
        'postal_code',
        'city',
        'region',
        'latitude',
        'longitude',
    ];

    public function __construct(
        private readonly AuditService $audit,
    ) {}

    /** @param array<string, mixed> $attributes */
    public function createForUser(User $actor, User $owner, array $attributes): Address
    {
        Gate::forUser($actor)->authorize('updateUserAddress', $owner);

        return DB::transaction(function () use ($actor, $owner, $attributes): Address {
            $lockedOwner = User::query()
                ->whereKey($owner->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            Gate::forUser($actor)->authorize('updateUserAddress', $lockedOwner);

            if ($lockedOwner->address()->exists()) {
                throw new InvalidArgumentException('This user already has a personal address.');
            }

            $validated = $this->validatedAttributes($attributes);
            $address = $lockedOwner->address()->make(
                $this->withLegacyAddressFields($validated),
            );
            $address->forceFill([
                'country_id' => $this->moroccoCountry()->getKey(),
                'created_by' => (string) $actor->getKey(),
            ])->save();

            $this->recordAddressAudit(
                actor: $actor,
                address: $address,
                action: 'created',
                eventKey: 'localization.address.created',
                newValues: $this->snapshot($address),
            );

            return $address;
        });
    }

    /** @param array<string, mixed> $attributes */
    public function updateForUser(User $actor, User $owner, array $attributes): Address
    {
        Gate::forUser($actor)->authorize('updateUserAddress', $owner);

        return DB::transaction(function () use ($actor, $owner, $attributes): Address {
            $lockedOwner = User::query()
                ->whereKey($owner->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            Gate::forUser($actor)->authorize('updateUserAddress', $lockedOwner);

            $address = $lockedOwner->address()
                ->lockForUpdate()
                ->first();

            if ($address === null) {
                throw new RuntimeException('The user does not have a personal address to update.');
            }

            return $this->updateAddress($actor, $address, $attributes);
        });
    }

    /** @param array<string, mixed> $attributes */
    public function createForCooperative(
        User $actor,
        Cooperative $cooperative,
        array $attributes,
    ): Address {
        Gate::forUser($actor)->authorize('createCooperativeAddress');

        return DB::transaction(function () use ($actor, $cooperative, $attributes): Address {
            $lockedCooperative = Cooperative::query()
                ->whereKey($cooperative->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            Gate::forUser($actor)->authorize('createCooperativeAddress');

            if ($lockedCooperative->address()->exists()) {
                throw new InvalidArgumentException('This cooperative already has an address.');
            }

            $validated = $this->validatedAttributes($attributes);
            $address = $lockedCooperative->address()->make(
                $this->withLegacyAddressFields($validated),
            );
            $address->forceFill([
                'country_id' => $this->moroccoCountry()->getKey(),
                'created_by' => (string) $actor->getKey(),
            ])->save();

            $this->recordAddressAudit(
                actor: $actor,
                address: $address,
                action: 'created',
                eventKey: 'localization.address.created',
                cooperativeId: (string) $lockedCooperative->getKey(),
                newValues: $this->snapshot($address),
            );

            return $address;
        });
    }

    /** @param array<string, mixed> $attributes */
    public function updateForCooperative(
        User $actor,
        Cooperative $cooperative,
        array $attributes,
    ): Address {
        Gate::forUser($actor)->authorize('updateCooperativeAddress', $cooperative);

        return DB::transaction(function () use ($actor, $cooperative, $attributes): Address {
            $lockedCooperative = Cooperative::query()
                ->whereKey($cooperative->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            Gate::forUser($actor)->authorize('updateCooperativeAddress', $lockedCooperative);

            $address = $lockedCooperative->address()
                ->lockForUpdate()
                ->first();

            if ($address === null) {
                throw new RuntimeException('The cooperative does not have an address to update.');
            }

            return $this->updateAddress($actor, $address, $attributes);
        });
    }

    /** @param array<string, mixed> $attributes */
    private function updateAddress(User $actor, Address $address, array $attributes): Address
    {
        $before = $this->snapshot($address);
        $validated = $this->validatedAttributes($attributes);
        $address->fill($this->withLegacyAddressFields(array_merge([
            'number' => $address->number,
            'street' => $address->street,
            'floor' => $address->floor,
            'region' => $address->region,
        ], $validated)));

        if (! $address->isDirty()) {
            return $address;
        }

        $address->updated_by = (string) $actor->getKey();
        $address->save();

        $this->recordAddressAudit(
            actor: $actor,
            address: $address,
            action: 'updated',
            eventKey: 'localization.address.updated',
            cooperativeId: $address->cooperative_id === null
                ? null
                : (string) $address->cooperative_id,
            oldValues: $before,
            newValues: $this->snapshot($address),
        );

        return $address;
    }

    /** @param array<string, mixed> $attributes
     * @return array<string, mixed>
     */
    private function withLegacyAddressFields(array $attributes): array
    {
        $number = trim((string) ($attributes['number'] ?? ''));
        $street = trim((string) ($attributes['street'] ?? ''));

        return array_merge($attributes, [
            // Keep legacy columns synchronized until dependent domains are migrated.
            'address_line_1' => trim($number.' '.$street),
            'address_line_2' => $attributes['floor'] ?? null,
            'state' => $attributes['region'] ?? null,
        ]);
    }

    /** @param array<string, mixed> $attributes
     * @return array<string, mixed>
     */
    private function validatedAttributes(array $attributes): array
    {
        $unknown = array_diff(array_keys($attributes), self::WRITABLE_FIELDS);

        if ($unknown !== []) {
            throw new InvalidArgumentException(
                'Unsupported address attributes: '.implode(', ', $unknown),
            );
        }

        $validated = Validator::make($attributes, [
            'number' => ['sometimes', 'nullable', 'string', 'max:50'],
            'street' => ['required', 'string', 'max:255'],
            'floor' => ['sometimes', 'nullable', 'string', 'max:50'],
            'postal_code' => ['sometimes', 'nullable', 'string', 'max:20'],
            'city' => ['required', 'string', 'max:120'],
            'region' => ['sometimes', 'nullable', 'string', 'max:120'],
            'latitude' => ['sometimes', 'nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['sometimes', 'nullable', 'numeric', 'between:-180,180'],
        ])->validate();

        if (isset($validated['street'])) {
            $validated['street'] = trim($validated['street']);
        }

        if (isset($validated['city'])) {
            $validated['city'] = trim($validated['city']);
        }

        foreach (['number', 'floor', 'postal_code', 'region'] as $field) {
            if (isset($validated[$field])) {
                $validated[$field] = trim($validated[$field]);
            }
        }

        return $validated;
    }

    private function moroccoCountry(): Country
    {
        $country = Country::query()
            ->where('code', 'MA')
            ->where('is_active', true)
            ->first();

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
        ];
    }

    /** @param array<string, mixed>|null $oldValues
     * @param array<string, mixed>|null $newValues
     */
    private function recordAddressAudit(
        User $actor,
        Address $address,
        string $action,
        string $eventKey,
        ?string $cooperativeId = null,
        ?array $oldValues = null,
        ?array $newValues = null,
    ): void {
        $auditCooperativeId = $cooperativeId ?? $address->cooperative_id;

        if ($auditCooperativeId === null && $address->user_id !== null) {
            $auditCooperativeId = User::query()
                ->whereKey($address->user_id)
                ->value('cooperative_id');
        }

        $this->audit->record(new AuditEntryDTO(
            eventKey: $eventKey,
            action: $action,
            actorType: User::class,
            actorId: (string) $actor->getKey(),
            cooperativeId: $auditCooperativeId === null
                ? null
                : (string) $auditCooperativeId,
            subjectType: Address::class,
            subjectId: (string) $address->getKey(),
            oldValues: $oldValues,
            newValues: $newValues,
        ));
    }
}
