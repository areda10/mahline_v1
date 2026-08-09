<?php

declare(strict_types=1);

namespace Tests\Framework\Foundation\Enums\Identity;

use App\Core\Foundation\Enums\Identity\UserStatus;
use App\Core\Foundation\Enums\Contracts\BaseEnumContract;
use Tests\TestCase;

final class UserStatusTest extends TestCase
{
    public function test_enum_implements_base_enum_contract(): void
    {
        $this->assertTrue(
            is_a(UserStatus::class, BaseEnumContract::class, true)
        );
    }

    public function test_enum_contains_expected_cases(): void
    {
        $this->assertSame(
            [
                UserStatus::ACTIVE,
                UserStatus::INACTIVE,
                UserStatus::SUSPENDED,
                UserStatus::PENDING,
                UserStatus::ARCHIVED,
            ],
            UserStatus::cases()
        );
    }

    public function test_all_returns_all_enum_cases(): void
    {
        $this->assertSame(
            UserStatus::cases(),
            UserStatus::all()
        );
    }

    public function test_values_returns_expected_values(): void
    {
        $this->assertSame(
            [
                'active',
                'inactive',
                'suspended',
                'pending',
                'archived',
            ],
            UserStatus::values()
        );
    }

    public function test_names_returns_expected_names(): void
    {
        $this->assertSame(
            [
                'ACTIVE',
                'INACTIVE',
                'SUSPENDED',
                'PENDING',
                'ARCHIVED',
            ],
            UserStatus::names()
        );
    }

    public function test_options_returns_expected_mapping(): void
    {
        $this->assertSame(
            [
                'active' => 'ACTIVE',
                'inactive' => 'INACTIVE',
                'suspended' => 'SUSPENDED',
                'pending' => 'PENDING',
                'archived' => 'ARCHIVED',
            ],
            UserStatus::options()
        );
    }

    public function test_labels_is_alias_of_options(): void
    {
        $this->assertSame(
            UserStatus::options(),
            UserStatus::labels()
        );
    }

    public function test_has_returns_true_for_valid_values(): void
    {
        $this->assertTrue(UserStatus::has('active'));
        $this->assertTrue(UserStatus::has('inactive'));
        $this->assertTrue(UserStatus::has('suspended'));
        $this->assertTrue(UserStatus::has('pending'));
        $this->assertTrue(UserStatus::has('archived'));
    }

    public function test_has_returns_false_for_invalid_value(): void
    {
        $this->assertFalse(
            UserStatus::has('unknown')
        );
    }

    public function test_has_name_returns_true_for_valid_names(): void
    {
        $this->assertTrue(UserStatus::hasName('ACTIVE'));
        $this->assertTrue(UserStatus::hasName('INACTIVE'));
        $this->assertTrue(UserStatus::hasName('SUSPENDED'));
        $this->assertTrue(UserStatus::hasName('PENDING'));
        $this->assertTrue(UserStatus::hasName('ARCHIVED'));
    }

    public function test_has_name_returns_false_for_invalid_name(): void
    {
        $this->assertFalse(
            UserStatus::hasName('UNKNOWN')
        );
    }

    public function test_try_from_returns_expected_case(): void
    {
        $this->assertSame(
            UserStatus::ACTIVE,
            UserStatus::tryFrom('active')
        );

        $this->assertSame(
            UserStatus::SUSPENDED,
            UserStatus::tryFrom('suspended')
        );

        $this->assertSame(
            UserStatus::ARCHIVED,
            UserStatus::tryFrom('archived')
        );
    }

    public function test_try_from_returns_null_for_invalid_value(): void
    {
        $this->assertNull(
            UserStatus::tryFrom('unknown')
        );
    }

    public function test_try_from_name_returns_expected_case(): void
    {
        $this->assertSame(
            UserStatus::ACTIVE,
            UserStatus::tryFromName('ACTIVE')
        );

        $this->assertSame(
            UserStatus::PENDING,
            UserStatus::tryFromName('PENDING')
        );
    }

    public function test_try_from_name_returns_null_for_invalid_name(): void
    {
        $this->assertNull(
            UserStatus::tryFromName('UNKNOWN')
        );
    }

    public function test_from_returns_expected_case(): void
    {
        $this->assertSame(
            UserStatus::ACTIVE,
            UserStatus::from('active')
        );

        $this->assertSame(
            UserStatus::ARCHIVED,
            UserStatus::from('archived')
        );
    }

    public function test_from_name_returns_expected_case(): void
    {
        $this->assertSame(
            UserStatus::SUSPENDED,
            UserStatus::fromName('SUSPENDED')
        );

        $this->assertSame(
            UserStatus::PENDING,
            UserStatus::fromName('PENDING')
        );
    }

    public function test_random_returns_valid_enum_case(): void
    {
        $this->assertContains(
            UserStatus::random(),
            UserStatus::cases()
        );
    }

    public function test_label_returns_case_name(): void
    {
        $this->assertSame(
            'ACTIVE',
            UserStatus::ACTIVE->label()
        );

        $this->assertSame(
            'SUSPENDED',
            UserStatus::SUSPENDED->label()
        );
    }

    public function test_value_returns_case_value(): void
    {
        $this->assertSame(
            'active',
            UserStatus::ACTIVE->value()
        );

        $this->assertSame(
            'archived',
            UserStatus::ARCHIVED->value()
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
            UserStatus::ACTIVE->toArray()
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
                [
                    'name' => 'PENDING',
                    'value' => 'pending',
                    'label' => 'PENDING',
                ],
                [
                    'name' => 'ARCHIVED',
                    'value' => 'archived',
                    'label' => 'ARCHIVED',
                ],
            ],
            UserStatus::toArrayList()
        );
    }
}