<?php

declare(strict_types=1);

namespace App\Domains\Localization\Contracts;

interface UserAddressLifecycleManager
{
    public function softDeleteUserAddressesById(string $userId, string $actorId): string;

    public function restoreLatestUserAddressesById(string $userId, string $actorId): void;
}
