<?php

declare(strict_types=1);

namespace App\Domains\Identity\Authorization\Services;

use App\Shared\Contracts\SuperAdminCooperativeAssignmentReader;
use App\Domains\Identity\Authorization\Models\SuperAdminCooperative;

final class SuperAdminCooperativeAssignmentReaderService implements SuperAdminCooperativeAssignmentReader
{
    public function isAssigned(string $superAdminId, string $cooperativeId): bool
    {
        return SuperAdminCooperative::query()
            ->where('super_admin_id', $superAdminId)
            ->where('cooperative_id', $cooperativeId)
            ->exists();
    }
}
