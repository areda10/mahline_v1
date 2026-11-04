<?php

declare(strict_types=1);

namespace App\Domains\Identity\UserManagement\Contracts;

use App\Domains\Cooperatives\Models\Cooperative;
use App\Domains\Identity\Users\Models\User;

interface SuperAdminCooperativeAssignmentService
{
    public function assignSuperAdminToCooperative(
        User $actor,
        User $superAdmin,
        Cooperative $cooperative,
    ): void;

    public function removeSuperAdminFromCooperative(
        User $actor,
        User $superAdmin,
        Cooperative $cooperative,
    ): void;
}
