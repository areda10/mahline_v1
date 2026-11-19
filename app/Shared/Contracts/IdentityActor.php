<?php

declare(strict_types=1);

namespace App\Shared\Contracts;

use Illuminate\Contracts\Auth\Authenticatable;

interface IdentityActor extends Authenticatable
{
    public function isAuthorized(): bool;

    public function hasRole(string $role): bool;

    /** @param list<string> $roles */
    public function hasAnyRole(array $roles): bool;

    public function hasPermission(string $permission): bool;

    public function cooperativeId(): ?string;
}
