<?php

declare(strict_types=1);

namespace App\Domains\Localization\Contracts;

use App\Domains\Localization\DTOs\UserAddressOwnerData;

interface UserAddressOwnerGateway
{
    /** Lock the user owner row in the caller's transaction. */
    public function lock(string $userId): UserAddressOwnerData;

    public function authorize(string $actorId, string $ability, string $userId): void;

    public function cooperativeId(string $userId): ?string;
}
