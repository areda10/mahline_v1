<?php

declare(strict_types=1);

namespace Tests\Framework\Domains\Identity\Authentication\Services;

use App\Domains\Identity\Authentication\Services\UnusualActivityDetectionService;
use App\Domains\Identity\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
}