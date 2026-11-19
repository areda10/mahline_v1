<?php

declare(strict_types=1);

namespace App\Domains\Localization\Contracts;

use App\Domains\Identity\Users\Models\User;

interface UserAddressLifecycleManager
{
    /** @param array<string, mixed> $attributes */
    public function savePrimaryAddressForUser(User $actor, string $ownerId, array $attributes): void;

    public function softDeleteUserAddressesById(string $userId, string $actorId): string;

    public function restoreLatestUserAddressesById(string $userId, string $actorId): void;
}
