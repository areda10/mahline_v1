<?php

declare(strict_types=1);

namespace Tests\Framework\Domains\Identity\Authentication\Models;

use App\Domains\Identity\Authentication\Models\KnownDevice;
use App\Domains\Identity\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class KnownDeviceTest extends TestCase
{
    use RefreshDatabase;

    public function test_known_devices_table_exists(): void
    {
        self::assertTrue(
            \Schema::hasTable('known_devices'),
        );
    }

    public function test_known_devices_table_contains_required_columns(): void
    {
        self::assertTrue(
            \Schema::hasColumns(
                'known_devices',
                [
                    'id',
                    'user_id',
                    'device',
                    'created_at',
                    'updated_at',
                ],
            ),
        );
    }

    public function test_user_and_device_combination_is_unique(): void
    {
        self::assertTrue(
            \Schema::hasIndex(
                'known_devices',
                'known_devices_user_id_device_unique',
            ),
        );
    }

    public function test_it_persists_a_known_device_for_a_user(): void
    {
        $user = User::factory()->create();

        $knownDevice = KnownDevice::query()->create([
            'user_id' => $user->id,
            'device' => 'iphone',
        ]);

        self::assertDatabaseHas('known_devices', [
            'id' => $knownDevice->id,
            'user_id' => $user->id,
            'device' => 'iphone',
        ]);
    }

    public function test_known_device_belongs_to_a_user(): void
    {
        $user = User::factory()->create();

        $knownDevice = KnownDevice::query()->create([
            'user_id' => $user->id,
            'device' => 'iphone',
        ]);

        self::assertTrue(
            $knownDevice->user->is($user),
        );
    }
}