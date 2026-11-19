<?php

declare(strict_types=1);

namespace App\Domains\Localization\DTOs;

final readonly class UserAddressOwnerData
{
    public function __construct(
        public string $id,
        public ?string $cooperativeId,
        public bool $isCustomer,
    ) {}
}
