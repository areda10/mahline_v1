<?php

declare(strict_types=1);

namespace App\Shared\Contracts;

interface SuperAdminCooperativeAssignmentReader
{
    public function isAssigned(string $superAdminId, string $cooperativeId): bool;
}
