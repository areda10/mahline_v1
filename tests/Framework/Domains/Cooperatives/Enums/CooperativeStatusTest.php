<?php

declare(strict_types=1);

namespace Tests\Framework\Domains\Cooperatives\Enums;

use App\Domains\Cooperatives\Enums\CooperativeStatus;
use PHPUnit\Framework\TestCase;

final class CooperativeStatusTest extends TestCase
{
    public function test_enum_is_string_backed(): void
    {
        $reflection = new \ReflectionEnum(CooperativeStatus::class);

        self::assertTrue($reflection->isBacked());
        self::assertSame('string', $reflection->getBackingType()?->getName());
    }

    public function test_pending_status_has_expected_value(): void
    {
        self::assertSame('pending', CooperativeStatus::PENDING->value);
    }

    public function test_active_status_has_expected_value(): void
    {
        self::assertSame('active', CooperativeStatus::ACTIVE->value);
    }

    public function test_suspended_status_has_expected_value(): void
    {
        self::assertSame('suspended', CooperativeStatus::SUSPENDED->value);
    }

    public function test_inactive_status_has_expected_value(): void
    {
        self::assertSame('inactive', CooperativeStatus::INACTIVE->value);
    }

    public function test_archived_status_has_expected_value(): void
    {
        self::assertSame('archived', CooperativeStatus::ARCHIVED->value);
    }

    public function test_all_status_values_are_unique(): void
    {
        $values = array_map(
            static fn (CooperativeStatus $status): string => $status->value,
            CooperativeStatus::cases(),
        );

        self::assertCount(
            count(array_unique($values)),
            $values,
        );
    }

    public function test_from_and_try_from_resolve_statuses(): void
    {
        self::assertSame(
            CooperativeStatus::ACTIVE,
            CooperativeStatus::from('active'),
        );

        self::assertSame(
            CooperativeStatus::SUSPENDED,
            CooperativeStatus::tryFrom('suspended'),
        );

        self::assertNull(
            CooperativeStatus::tryFrom('unknown'),
        );
    }
}
