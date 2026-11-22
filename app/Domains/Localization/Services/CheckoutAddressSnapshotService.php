<?php

declare(strict_types=1);

namespace App\Domains\Localization\Services;

use App\Core\Foundation\Services\BaseService;
use App\Domains\Localization\Contracts\CheckoutAddressReader;
use App\Domains\Localization\DTOs\CheckoutAddressSnapshot;
use App\Domains\Localization\Models\Address;
use Illuminate\Validation\ValidationException;

final class CheckoutAddressSnapshotService extends BaseService implements CheckoutAddressReader
{
    public function snapshotForCustomer(string $customerId, string $addressId): CheckoutAddressSnapshot
    {
        $address = Address::query()
            ->with('country')
            ->whereKey($addressId)
            ->where('user_id', $customerId)
            ->whereNull('cooperative_id')
            ->first();

        if ($address === null || $address->country === null || ! $address->country->is_active) {
            throw ValidationException::withMessages([
                'delivery_address_id' => ['Cette adresse de livraison est introuvable ou indisponible.'],
            ]);
        }

        foreach (['street', 'city'] as $field) {
            if (trim((string) $address->{$field}) === '') {
                throw ValidationException::withMessages([
                    'delivery_address_id' => ['L’adresse sélectionnée est incomplète.'],
                ]);
            }
        }

        return new CheckoutAddressSnapshot([
            'address_id' => (string) $address->getKey(),
            'number' => $address->number,
            'street' => $address->street,
            'floor' => $address->floor,
            'postal_code' => $address->postal_code,
            'city' => $address->city,
            'region' => $address->region,
            'country_id' => (string) $address->country_id,
            'country_code' => $address->country->code,
            'country_name' => $address->country->name,
            'latitude' => $address->latitude,
            'longitude' => $address->longitude,
        ]);
    }
}
