<?php

declare(strict_types=1);

namespace Tests\Framework\Domains\Identity\Authentication\Services;

use App\Domains\Identity\Authentication\Models\KnownDevice;
use App\Domains\Identity\Authentication\Services\UnusualActivityDetectionService;
use App\Domains\Identity\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

final class UnusualActivityDetectionServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_detects_a_new_device_for_a_user(): void
    {
        $user = User::factory()->create();

        $service = app(UnusualActivityDetectionService::class);

        self::assertTrue(
            $service->isNewDevice(
                user: $user,
                device: 'iPhone',
            ),
        );
    }

    public function test_it_does_not_detect_a_known_device_as_new(): void
    {
        $user = User::factory()->create();

        $service = app(UnusualActivityDetectionService::class);

        $service->rememberDevice(
            user: $user,
            device: 'iPhone',
        );

        self::assertFalse(
            $service->isNewDevice(
                user: $user,
                device: 'iPhone',
            ),
        );
    }

    public function test_a_device_known_by_one_user_is_new_for_another_user(): void
    {
        $firstUser = User::factory()->create();
        $secondUser = User::factory()->create();

        $service = app(UnusualActivityDetectionService::class);

        $service->rememberDevice(
            user: $firstUser,
            device: 'iPhone',
        );

        self::assertTrue(
            $service->isNewDevice(
                user: $secondUser,
                device: 'iPhone',
            ),
        );
    }

    public function test_device_comparison_is_case_and_whitespace_insensitive(): void
    {
        $user = User::factory()->create();

        $service = app(UnusualActivityDetectionService::class);

        $service->rememberDevice(
            user: $user,
            device: ' iPhone ',
        );

        self::assertFalse(
            $service->isNewDevice(
                user: $user,
                device: 'iphone',
            ),
        );
    }

    public function test_it_rejects_an_empty_device(): void
    {
        $user = User::factory()->create();

        $service = app(UnusualActivityDetectionService::class);

        self::expectException(\InvalidArgumentException::class);

        $service->rememberDevice(
            user: $user,
            device: '   ',
        );
    }

    public function test_remembering_the_same_device_is_idempotent(): void
    {
        $user = User::factory()->create();

        $service = app(UnusualActivityDetectionService::class);

        $service->rememberDevice(
            user: $user,
            device: 'iPhone',
        );

        $service->rememberDevice(
            user: $user,
            device: 'iphone',
        );

        self::assertFalse(
            $service->isNewDevice(
                user: $user,
                device: 'IPHONE',
            ),
        );
    }

    public function test_a_remembered_device_remains_known_after_service_recreation(): void
    {
        $user = User::factory()->create();

        $firstService = app(UnusualActivityDetectionService::class);

        $firstService->rememberDevice(
            user: $user,
            device: 'iPhone',
        );

        $secondService = app(UnusualActivityDetectionService::class);

        self::assertFalse(
            $secondService->isNewDevice(
                user: $user,
                device: 'iPhone',
            ),
        );
    }

    public function test_deleted_device_is_detected_as_new_again(): void
    {
        $user = User::factory()->create();

        $service = app(UnusualActivityDetectionService::class);

        $service->rememberDevice(
            user: $user,
            device: 'iPhone',
        );

        KnownDevice::query()
            ->where('user_id', $user->id)
            ->where('device', 'iphone')
            ->delete();

        self::assertTrue(
            $service->isNewDevice(
                user: $user,
                device: 'iPhone',
            ),
        );
    }

    public function test_switching_between_known_devices_is_not_unusual(): void
    {
        $user = User::factory()->create();

        $service = app(UnusualActivityDetectionService::class);

        $service->rememberDevice(
            user: $user,
            device: 'iPhone',
        );

        $service->rememberDevice(
            user: $user,
            device: 'MacBook',
        );

        self::assertFalse(
            $service->isNewDevice(
                user: $user,
                device: 'iPhone',
            ),
        );

        self::assertFalse(
            $service->isNewDevice(
                user: $user,
                device: 'MacBook',
            ),
        );
    }

    public function test_remember_device_stores_normalized_value(): void
    {
        $user = User::factory()->create();

        $service = app(UnusualActivityDetectionService::class);

        $service->rememberDevice(
            user: $user,
            device: '  iPhone  ',
        );

        self::assertDatabaseHas('known_devices', [
            'user_id' => $user->id,
            'device' => 'iphone',
        ]);
    }

    public function test_it_rejects_a_device_longer_than_100_characters(): void
    {
        $user = User::factory()->create();

        $service = app(UnusualActivityDetectionService::class);

        $this->expectException(InvalidArgumentException::class);

        $service->rememberDevice(
            user: $user,
            device: str_repeat('a', 101),
        );
    }

    public function test_it_accepts_a_device_of_exactly_100_characters(): void
    {
        $user = User::factory()->create();

        $service = app(UnusualActivityDetectionService::class);

        $device = str_repeat('a', 100);

        $service->rememberDevice(
            user: $user,
            device: $device,
        );

        self::assertDatabaseHas('known_devices', [
            'user_id' => $user->id,
            'device' => $device,
        ]);
    }

    public function test_the_same_device_can_be_remembered_for_two_different_users(): void
    {
        $firstUser = User::factory()->create();
        $secondUser = User::factory()->create();

        $service = app(UnusualActivityDetectionService::class);

        $service->rememberDevice(
            user: $firstUser,
            device: 'iPhone',
        );

        $service->rememberDevice(
            user: $secondUser,
            device: 'iPhone',
        );

        self::assertDatabaseCount('known_devices', 2);

        self::assertDatabaseHas('known_devices', [
            'user_id' => $firstUser->id,
            'device' => 'iphone',
        ]);

        self::assertDatabaseHas('known_devices', [
            'user_id' => $secondUser->id,
            'device' => 'iphone',
        ]);
    }

    public function test_deleting_a_user_deletes_its_known_devices(): void
    {
        $user = User::factory()->create();

        $service = app(UnusualActivityDetectionService::class);

        $service->rememberDevice(
            user: $user,
            device: 'iPhone',
        );

        self::assertDatabaseHas('known_devices', [
            'user_id' => $user->id,
            'device' => 'iphone',
        ]);

        $user->forceDelete();

        self::assertDatabaseMissing('known_devices', [
            'user_id' => $user->id,
            'device' => 'iphone',
        ]);
    }

    public function test_checking_a_device_does_not_create_a_known_device(): void
    {
        $user = User::factory()->create();

        $service = app(UnusualActivityDetectionService::class);

        self::assertTrue(
            $service->isNewDevice(
                user: $user,
                device: 'iPhone',
            ),
        );

        self::assertDatabaseMissing('known_devices', [
            'user_id' => $user->id,
            'device' => 'iphone',
        ]);
    }

    public function test_remembering_a_known_device_does_not_create_a_duplicate(): void
    {
        $user = User::factory()->create();

        $service = app(UnusualActivityDetectionService::class);

        $service->rememberDevice(
            user: $user,
            device: 'iPhone',
        );

        $service->rememberDevice(
            user: $user,
            device: ' IPHONE ',
        );

        self::assertDatabaseCount('known_devices', 1);

        self::assertDatabaseHas('known_devices', [
            'user_id' => $user->id,
            'device' => 'iphone',
        ]);
    }

    public function test_it_detects_a_known_100_character_device_after_normalization(): void
    {
        $user = User::factory()->create();

        $service = app(UnusualActivityDetectionService::class);

        $device = str_repeat('a', 100);

        $service->rememberDevice(
            user: $user,
            device: $device,
        );

        self::assertFalse(
            $service->isNewDevice(
                user: $user,
                device: '  '.strtoupper($device).'  ',
            ),
        );
    }

    public function test_it_rejects_a_device_longer_than_100_characters_when_checking(): void
    {
        $user = User::factory()->create();

        $service = app(UnusualActivityDetectionService::class);

        $this->expectException(InvalidArgumentException::class);

        $service->isNewDevice(
            user: $user,
            device: str_repeat('a', 101),
        );
    }

    public function test_a_different_device_is_detected_as_new(): void
    {
        $user = User::factory()->create();

        $service = app(UnusualActivityDetectionService::class);

        $service->rememberDevice(
            user: $user,
            device: 'iPhone',
        );

        self::assertTrue(
            $service->isNewDevice(
                user: $user,
                device: 'Android',
            ),
        );

        self::assertDatabaseMissing('known_devices', [
            'user_id' => $user->id,
            'device' => 'android',
        ]);
    }

    public function test_rejected_device_is_not_persisted(): void
    {
        $user = User::factory()->create();

        $service = app(UnusualActivityDetectionService::class);

        $this->expectException(InvalidArgumentException::class);

        try {
            $service->rememberDevice(
                user: $user,
                device: str_repeat('a', 101),
            );
        } finally {
            self::assertDatabaseMissing('known_devices', [
                'user_id' => $user->id,
                'device' => str_repeat('a', 101),
            ]);
        }
    }

    public function test_remembering_normalization_variants_creates_only_one_device(): void
    {
        $user = User::factory()->create();

        $service = app(UnusualActivityDetectionService::class);

        $service->rememberDevice(
            user: $user,
            device: '  iPhone  ',
        );

        $service->rememberDevice(
            user: $user,
            device: 'IPHONE',
        );

        $service->rememberDevice(
            user: $user,
            device: 'iphone',
        );

        self::assertDatabaseCount('known_devices', 1);

        self::assertDatabaseHas('known_devices', [
            'user_id' => $user->id,
            'device' => 'iphone',
        ]);
    }

    public function test_remembering_two_different_devices_creates_two_records(): void
    {
        $user = User::factory()->create();

        $service = app(UnusualActivityDetectionService::class);

        $service->rememberDevice(
            user: $user,
            device: 'iPhone',
        );

        $service->rememberDevice(
            user: $user,
            device: 'MacBook',
        );

        self::assertDatabaseCount('known_devices', 2);

        self::assertDatabaseHas('known_devices', [
            'user_id' => $user->id,
            'device' => 'iphone',
        ]);

        self::assertDatabaseHas('known_devices', [
            'user_id' => $user->id,
            'device' => 'macbook',
        ]);
    }

    public function test_detecting_a_new_device_does_not_remember_it_automatically(): void
    {
        $user = User::factory()->create();

        $service = app(UnusualActivityDetectionService::class);

        self::assertTrue(
            $service->isNewDevice(
                user: $user,
                device: 'Android',
            ),
        );

        self::assertDatabaseCount('known_devices', 0);

        self::assertDatabaseMissing('known_devices', [
            'user_id' => $user->id,
            'device' => 'android',
        ]);
    }
}