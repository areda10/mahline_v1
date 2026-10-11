<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domains\Identity\Users\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

final class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $this->call([
            RoleSeeder::class,
        ]);

        User::factory()->create([
            'first_name' => 'Test',
            'last_name' => 'User',
            'display_name' => 'Test User',
            'email' => 'test@example.com',
        ]);
    }
}