<?php

declare(strict_types=1);

namespace Tests\Support\Concerns;

use App\Domains\Cooperatives\Models\Cooperative;
use App\Domains\Identity\Authorization\Models\Role;
use App\Domains\Identity\Users\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;

trait CreatesAuthorizedCooperativeAdmin
{
    protected function createCooperativeAdminFor(
        Cooperative $cooperative,
    ): User {
        $this->seed([
            RoleSeeder::class,
            PermissionSeeder::class,
            RolePermissionSeeder::class,
        ]);

        $role = Role::query()
            ->where('slug', 'cooperative_admin')
            ->where('is_active', true)
            ->firstOrFail();

        $user = User::factory()->create([
            'cooperative_id' => $cooperative->getKey(),
        ]);

        $user->roles()->syncWithoutDetaching([
            $role->getKey(),
        ]);

        return $user;
    }
}
