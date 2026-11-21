<?php

declare(strict_types=1);

namespace App\Domains\Identity\Authorization\Services;

use App\Domains\Identity\Authorization\Models\SuperAdminCooperative;
use App\Shared\Contracts\SuperAdminCooperativeAssignmentReader;

final class SuperAdminCooperativeAssignmentReaderService implements SuperAdminCooperativeAssignmentReader
{
    public function cooperativeIdsFor(string $superAdminId): array
    {
        return SuperAdminCooperative::query()
            ->where('super_admin_id', $superAdminId)
            ->orderBy('cooperative_id')
            ->pluck('cooperative_id')
            ->map(static fn ($id): string => (string) $id)
            ->all();
    }

    public function isAssigned(string $superAdminId, string $cooperativeId): bool
    {
        return SuperAdminCooperative::query()
            ->where('super_admin_id', $superAdminId)
            ->where('cooperative_id', $cooperativeId)
            ->exists();
    }
}
