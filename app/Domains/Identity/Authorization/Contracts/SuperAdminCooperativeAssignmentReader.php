<?php

declare(strict_types=1);

namespace App\Domains\Identity\Authorization\Contracts;

interface SuperAdminCooperativeAssignmentReader
{
    public function isAssigned(string $superAdminId, string $cooperativeId): bool;
}
