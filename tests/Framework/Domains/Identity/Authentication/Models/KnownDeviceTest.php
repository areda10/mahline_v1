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

    private string $loginUrl;

    private string $userAgent =
        'Mozilla/5.0 (Windows NT 10.0; Win64; x64) '
        . 'AppleWebKit/537.36 (KHTML, like Gecko) '
        . 'Chrome/140.0.0.0 Safari/537.36';

    protected function setUp(): void
    {
        parent::setUp();

        $this->loginUrl = route('authentication.login.store');
    }

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
    //function added from other files 
    public function test_successful_authentication_remembers_a_new_device(): void
    {
        $user = User::factory()->create([
            'email' => 'new-device@example.com',
            'password' => 'Password123',
            'status' => 'active',
        ]);

        $response = $this
            ->withHeader('User-Agent', $this->userAgent)
            ->postJson($this->loginUrl, [
                'email' => $user->email,
                'password' => 'Password123',
            ]);

        $response->assertOk();

        $this->assertDatabaseHas('known_devices', [
            'user_id' => $user->getKey(),
            'device' => 'windows',
        ]);
    }

    public function test_successful_authentication_does_not_duplicate_a_known_device(): void
    {
        $user = User::factory()->create([
            'email' => 'known-device@example.com',
            'password' => 'Password123',
            'status' => 'active',
        ]);

        KnownDevice::query()->create([
            'user_id' => $user->getKey(),
            'device' => 'windows',
        ]);

        $response = $this
            ->withHeader('User-Agent', $this->userAgent)
            ->postJson($this->loginUrl, [
                'email' => $user->email,
                'password' => 'Password123',
            ]);

        $response->assertOk();

        $this->assertSame(
            1,
            KnownDevice::query()
                ->where('user_id', $user->getKey())
                ->where('device', 'windows')
                ->count(),
        );
    }

    public function test_successful_authentication_remembers_each_new_device(): void
    {
        $user = User::factory()->create([
            'email' => 'multiple-devices@example.com',
            'password' => 'Password123',
            'status' => 'active',
        ]);

        $computerUserAgent =
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64) '
            . 'AppleWebKit/537.36 '
            . 'Chrome/140.0.0.0 Safari/537.36';

        $phoneUserAgent =
            'Mozilla/5.0 (iPhone; CPU iPhone OS 18_0 like Mac OS X) '
            . 'AppleWebKit/605.1.15 '
            . 'Version/18.0 Mobile/15E148 Safari/604.1';

        $computerResponse = $this
            ->withHeader('User-Agent', $computerUserAgent)
            ->postJson($this->loginUrl, [
                'email' => $user->email,
                'password' => 'Password123',
            ]);

        $computerResponse->assertOk();

        $phoneResponse = $this
            ->withHeader('User-Agent', $phoneUserAgent)
            ->postJson($this->loginUrl, [
                'email' => $user->email,
                'password' => 'Password123',
            ]);

        $phoneResponse->assertOk();

        $this->assertDatabaseHas('known_devices', [
            'user_id' => $user->getKey(),
            'device' => 'windows',
        ]);

        $this->assertDatabaseHas('known_devices', [
            'user_id' => $user->getKey(),
            'device' => 'iphone',
        ]);

        $this->assertSame(
            2,
            KnownDevice::query()
                ->where('user_id', $user->getKey())
                ->count(),
        );
    }
}