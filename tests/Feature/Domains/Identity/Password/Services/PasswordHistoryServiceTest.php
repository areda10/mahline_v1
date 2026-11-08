<?php

declare(strict_types=1);

namespace Tests\Feature\Domains\Identity\Password\Services;

use App\Domains\Identity\Password\Services\PasswordHistoryService;
use App\Domains\Identity\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use InvalidArgumentException;
use Tests\TestCase;

final class PasswordHistoryServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_rejects_the_current_password(): void
    {
        $user = User::factory()->create([
            'password' => 'CurrentPassword12!',
        ]);

        $this->expectException(InvalidArgumentException::class);

        app(PasswordHistoryService::class)->assertNotReused(
            $user,
            'CurrentPassword12!',
        );
    }

    public function test_it_rejects_any_of_the_five_previous_passwords(): void
    {
        $user = User::factory()->create([
            'password' => 'CurrentPassword12!',
        ]);

        DB::table('user_password_histories')->insert([
            'id' => (string) \Illuminate\Support\Str::ulid(),
            'user_id' => $user->getKey(),
            'password_hash' => Hash::make('PreviousPassword12!'),
            'created_at' => now(),
        ]);

        $this->expectException(InvalidArgumentException::class);

        app(PasswordHistoryService::class)->assertNotReused(
            $user,
            'PreviousPassword12!',
        );
    }

    public function test_it_keeps_only_the_five_most_recent_previous_hashes(): void
    {
        $user = User::factory()->create([
            'password' => 'CurrentPassword12!',
        ]);

        $history = app(PasswordHistoryService::class);

        for ($i = 1; $i <= 6; $i++) {
            $user->password = 'PasswordNumber' . $i . '!';
            $user->save();

            $history->archiveCurrentPassword($user);
        }

        $entries = DB::table('user_password_histories')
            ->where('user_id', $user->getKey())
            ->orderBy('created_at')
            ->orderBy('id')
            ->get();

        $this->assertCount(5, $entries);
    }
}
