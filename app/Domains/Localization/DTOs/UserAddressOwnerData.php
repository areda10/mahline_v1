<?php

declare(strict_types=1);

namespace App\Domains\Localization\DTOs;

final readonly class UserAddressOwnerData
{
    /**
     * @param list<string> $roles
     */
    public function __construct(
        public string $id,
        public ?string $cooperativeId,
        public bool $isCustomer,
        public array $roles,
    ) {}

    public function hasRole(string $role): bool
    {
        return in_array($role, $this->roles, true);
    }

    /** @param list<string> $roles */
    public function hasAnyRole(array $roles): bool
    {
        return array_intersect($roles, $this->roles) !== [];
    }
}
