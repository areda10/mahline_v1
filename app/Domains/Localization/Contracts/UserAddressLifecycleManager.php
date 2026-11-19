<?php

declare(strict_types=1);

namespace App\Domains\Localization\Contracts;

use App\Domains\Localization\Models\Address;

interface UserAddressLifecycleManager
{
    /** @param array<string, mixed> $attributes */
    public function savePrimaryAddressForUser(string $actorId, string $ownerId, array $attributes): void;

    /** @param array<string, mixed> $attributes */
    public function createForUser(string $actorId, string $ownerId, array $attributes): \App\Domains\Localization\Models\Address;

    /** @param array<string, mixed> $attributes */
    public function updateForUser(string $actorId, string $ownerId, array $attributes): \App\Domains\Localization\Models\Address;

    public function setPrimaryForUser(string $actorId, string $ownerId, string $addressId): \App\Domains\Localization\Models\Address;

    public function deleteForUser(string $actorId, string $ownerId, string $addressId): void;

    public function softDeleteUserAddressesById(string $userId, string $actorId): string;

    public function restoreLatestUserAddressesById(string $userId, string $actorId): void;
}
