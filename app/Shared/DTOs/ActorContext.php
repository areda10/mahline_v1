<?php

declare(strict_types=1);

namespace App\Shared\DTOs;

final readonly class ActorContext
{
    /**
     * @param  list<string>  $roles
     * @param  list<string>  $permissions
     */
    public function __construct(
        public string $id,
        public string $actorType,
        public array $roles,
        public array $permissions,
        public ?string $cooperativeId,
        public bool $authorized,
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

    public function hasPermission(string $permission): bool
    {
        return in_array($permission, $this->permissions, true);
    }
}
