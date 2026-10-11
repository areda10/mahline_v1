<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domains\Identity\Authorization\Models\Role;
use Illuminate\Database\Seeder;

final class RoleSeeder extends Seeder
{
    /**
     * Seed the MAHLINE business roles.
     */
    public function run(): void
    {
        $roles = [
            'super_admin_root' => 'Super Admin Root',
            'super_admin' => 'Super Admin',
            'cooperative_admin' => 'Cooperative Admin',
            'user' => 'User',
        ];

        foreach ($roles as $slug => $name) {
            Role::query()->firstOrCreate(
                ['slug' => $slug],
                [
                    'name' => $name,
                    'is_active' => true,
                ],
            );
        }
    }
}