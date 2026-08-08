<?php

declare(strict_types=1);

namespace Tests\Framework\Foundation\Enums;

use App\Core\Foundation\Enums\BaseEnum;
use App\Core\Foundation\Enums\Contracts\BaseEnumContract;
use App\Core\Foundation\Enums\Exceptions\InvalidEnumValueException;
use PHPUnit\Framework\TestCase;

final class BaseEnumTest extends TestCase
{
    public function test_enum_implements_base_enum_contract(): void
    {
        $this->assertTrue(
            is_subclass_of(
                TestStatus::class,
                BaseEnumContract::class
            )
        );
    }

    public function test_all_returns_all_enum_cases(): void
    {
        $this->assertSame(
            [
                TestStatus::ACTIVE,
                TestStatus::INACTIVE,
                TestStatus::SUSPENDED,
            ],
            TestStatus::all()
        );
    }

    public function test_values_returns_all_enum_values(): void
    {
        $this->assertSame(
            [
                'active',
                'inactive',
                'suspended',
            ],
            TestStatus::values()
        );
    }

    public function test_names_returns_all_enum_names(): void
    {
        $this->assertSame(
            [
                'ACTIVE',
                'INACTIVE',
                'SUSPENDED',
            ],
            TestStatus::names()
        );
    }

    public function test_options_returns_value_to_name_mapping(): void
    {
        $this->assertSame(
            [
                'active' => 'ACTIVE',
                'inactive' => 'INACTIVE',
                'suspended' => 'SUSPENDED',
            ],
            TestStatus::options()
        );
    }

    public function test_labels_is_alias_of_options(): void
    {
        $this->assertSame(
            TestStatus::options(),
            TestStatus::labels()
        );
    }

    public function test_has_returns_true_for_existing_value(): void
    {
        $this->assertTrue(
            TestStatus::has('active')
        );
    }

    public function test_has_returns_false_for_unknown_value(): void
    {
        $this->assertFalse(
            TestStatus::has('unknown')
        );
    }

    public function test_has_name_returns_true_for_existing_name(): void
    {
        $this->assertTrue(
            TestStatus::hasName('ACTIVE')
        );
    }

    public function test_has_name_returns_false_for_unknown_name(): void
    {
        $this->assertFalse(
            TestStatus::hasName('UNKNOWN')
        );
    }

    public function test_try_from_name_returns_enum_case(): void
    {
        $this->assertSame(
            TestStatus::ACTIVE,
            TestStatus::tryFromName('ACTIVE')
        );
    }

    public function test_try_from_name_returns_null_for_unknown_name(): void
    {
        $this->assertNull(
            TestStatus::tryFromName('UNKNOWN')
        );
    }

    public function test_from_name_returns_enum_case(): void
    {
        $this->assertSame(
            TestStatus::INACTIVE,
            TestStatus::fromName('INACTIVE')
        );
    }

    public function test_from_name_throws_for_unknown_name(): void
    {
        $this->expectException(
            InvalidEnumValueException::class
        );

        TestStatus::fromName('UNKNOWN');
    }

    public function test_from_name_exception_contains_enum_information(): void
    {
        try {
            TestStatus::fromName('UNKNOWN');

            $this->fail('Expected InvalidEnumValueException was not thrown.');
        } catch (InvalidEnumValueException $exception) {
            $this->assertStringContainsString(
                'UNKNOWN',
                $exception->getMessage()
            );

            $this->assertStringContainsString(
                TestStatus::class,
                $exception->getMessage()
            );
        }
    }

    public function test_random_returns_valid_enum_case(): void
    {
        $this->assertContains(
            TestStatus::random(),
            TestStatus::cases()
        );
    }

    public function test_label_returns_enum_name_by_default(): void
    {
        $this->assertSame(
            'ACTIVE',
            TestStatus::ACTIVE->label()
        );
    }

    public function test_value_returns_enum_value(): void
    {
        $this->assertSame(
            'active',
            TestStatus::ACTIVE->value()
        );
    }

    public function test_to_array_returns_expected_structure(): void
    {
        $this->assertSame(
            [
                'name' => 'ACTIVE',
                'value' => 'active',
                'label' => 'ACTIVE',
            ],
            TestStatus::ACTIVE->toArray()
        );
    }

    public function test_to_array_list_returns_all_cases(): void
    {
        $this->assertSame(
            [
                [
                    'name' => 'ACTIVE',
                    'value' => 'active',
                    'label' => 'ACTIVE',
                ],
                [
                    'name' => 'INACTIVE',
                    'value' => 'inactive',
                    'label' => 'INACTIVE',
                ],
                [
                    'name' => 'SUSPENDED',
                    'value' => 'suspended',
                    'label' => 'SUSPENDED',
                ],
            ],
            TestStatus::toArrayList()
        );
    }
}

/**
 * Test enum used exclusively by BaseEnumTest.
 */
enum TestStatus: string implements BaseEnumContract
{
    use BaseEnum;

    case ACTIVE = 'active';
    case INACTIVE = 'inactive';
    case SUSPENDED = 'suspended';
}
