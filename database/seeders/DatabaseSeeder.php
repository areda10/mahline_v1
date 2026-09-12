<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domains\Identity\Users\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        User::factory()->create([
            'first_name' => 'Test',
            'last_name' => 'User',
            'display_name' => 'Test User',
            'email' => 'test@example.com',
        ]);
    }
}