<?php

declare(strict_types=1);

namespace Tests\Framework\Enums\Database;

use App\Core\Enums\Database\ForeignKeyAction;
use PHPUnit\Framework\TestCase;

final class ForeignKeyActionTest extends TestCase
{
    public function test_enum_contains_expected_cases(): void
    {
        $this->assertSame(
            [
                ForeignKeyAction::CASCADE,
                ForeignKeyAction::RESTRICT,
                ForeignKeyAction::SET_NULL,
                ForeignKeyAction::NO_ACTION,
            ],
            ForeignKeyAction::cases()
        );
    }

    public function test_all_returns_all_enum_cases(): void
    {
        $this->assertSame(
            ForeignKeyAction::cases(),
            ForeignKeyAction::all()
        );
    }

    public function test_values_returns_expected_values(): void
    {
        $this->assertSame(
            [
                'cascade',
                'restrict',
                'set_null',
                'no_action',
            ],
            ForeignKeyAction::values()
        );
    }

    public function test_names_returns_expected_names(): void
    {
        $this->assertSame(
            [
                'CASCADE',
                'RESTRICT',
                'SET_NULL',
                'NO_ACTION',
            ],
            ForeignKeyAction::names()
        );
    }

    public function test_options_returns_expected_mapping(): void
    {
        $this->assertSame(
            [
                'cascade' => 'CASCADE',
                'restrict' => 'RESTRICT',
                'set_null' => 'SET_NULL',
                'no_action' => 'NO_ACTION',
            ],
            ForeignKeyAction::options()
        );
    }

    public function test_labels_is_alias_of_options(): void
    {
        $this->assertSame(
            ForeignKeyAction::options(),
            ForeignKeyAction::labels()
        );
    }

    public function test_has_returns_true_for_valid_values(): void
    {
        $this->assertTrue(
            ForeignKeyAction::has('cascade')
        );

        $this->assertTrue(
            ForeignKeyAction::has('restrict')
        );

        $this->assertTrue(
            ForeignKeyAction::has('set_null')
        );

        $this->assertTrue(
            ForeignKeyAction::has('no_action')
        );
    }

    public function test_has_returns_false_for_invalid_value(): void
    {
        $this->assertFalse(
            ForeignKeyAction::has('invalid')
        );
    }

    public function test_has_name_returns_true_for_valid_names(): void
    {
        $this->assertTrue(
            ForeignKeyAction::hasName('CASCADE')
        );

        $this->assertTrue(
            ForeignKeyAction::hasName('RESTRICT')
        );

        $this->assertTrue(
            ForeignKeyAction::hasName('SET_NULL')
        );

        $this->assertTrue(
            ForeignKeyAction::hasName('NO_ACTION')
        );
    }

    public function test_has_name_returns_false_for_invalid_name(): void
    {
        $this->assertFalse(
            ForeignKeyAction::hasName('INVALID')
        );
    }

    public function test_try_from_name_returns_expected_case(): void
    {
        $this->assertSame(
            ForeignKeyAction::CASCADE,
            ForeignKeyAction::tryFromName('CASCADE')
        );

        $this->assertSame(
            ForeignKeyAction::RESTRICT,
            ForeignKeyAction::tryFromName('RESTRICT')
        );

        $this->assertSame(
            ForeignKeyAction::SET_NULL,
            ForeignKeyAction::tryFromName('SET_NULL')
        );

        $this->assertSame(
            ForeignKeyAction::NO_ACTION,
            ForeignKeyAction::tryFromName('NO_ACTION')
        );
    }

    public function test_try_from_name_returns_null_for_invalid_name(): void
    {
        $this->assertNull(
            ForeignKeyAction::tryFromName('INVALID')
        );
    }

    public function test_from_name_returns_expected_case(): void
    {
        $this->assertSame(
            ForeignKeyAction::CASCADE,
            ForeignKeyAction::fromName('CASCADE')
        );

        $this->assertSame(
            ForeignKeyAction::RESTRICT,
            ForeignKeyAction::fromName('RESTRICT')
        );

        $this->assertSame(
            ForeignKeyAction::SET_NULL,
            ForeignKeyAction::fromName('SET_NULL')
        );

        $this->assertSame(
            ForeignKeyAction::NO_ACTION,
            ForeignKeyAction::fromName('NO_ACTION')
        );
    }

    public function test_from_name_throws_for_invalid_name(): void
    {
        $this->expectException(\App\Core\Foundation\Enums\Exceptions\InvalidEnumValueException::class);

        ForeignKeyAction::fromName('INVALID');
    }

    public function test_try_from_returns_expected_case(): void
    {
        $this->assertSame(
            ForeignKeyAction::CASCADE,
            ForeignKeyAction::tryFrom('cascade')
        );

        $this->assertSame(
            ForeignKeyAction::RESTRICT,
            ForeignKeyAction::tryFrom('restrict')
        );

        $this->assertSame(
            ForeignKeyAction::SET_NULL,
            ForeignKeyAction::tryFrom('set_null')
        );

        $this->assertSame(
            ForeignKeyAction::NO_ACTION,
            ForeignKeyAction::tryFrom('no_action')
        );
    }

    public function test_try_from_returns_null_for_invalid_value(): void
    {
        $this->assertNull(
            ForeignKeyAction::tryFrom('invalid')
        );
    }

    public function test_label_returns_case_name(): void
    {
        $this->assertSame(
            'CASCADE',
            ForeignKeyAction::CASCADE->label()
        );

        $this->assertSame(
            'RESTRICT',
            ForeignKeyAction::RESTRICT->label()
        );

        $this->assertSame(
            'SET_NULL',
            ForeignKeyAction::SET_NULL->label()
        );

        $this->assertSame(
            'NO_ACTION',
            ForeignKeyAction::NO_ACTION->label()
        );
    }

    public function test_value_returns_case_value(): void
    {
        $this->assertSame(
            'cascade',
            ForeignKeyAction::CASCADE->value()
        );

        $this->assertSame(
            'restrict',
            ForeignKeyAction::RESTRICT->value()
        );

        $this->assertSame(
            'set_null',
            ForeignKeyAction::SET_NULL->value()
        );

        $this->assertSame(
            'no_action',
            ForeignKeyAction::NO_ACTION->value()
        );
    }

    public function test_to_array_returns_expected_structure(): void
    {
        $this->assertSame(
            [
                'name' => 'CASCADE',
                'value' => 'cascade',
                'label' => 'CASCADE',
            ],
            ForeignKeyAction::CASCADE->toArray()
        );
    }

    public function test_to_array_list_returns_all_cases(): void
    {
        $this->assertSame(
            [
                [
                    'name' => 'CASCADE',
                    'value' => 'cascade',
                    'label' => 'CASCADE',
                ],
                [
                    'name' => 'RESTRICT',
                    'value' => 'restrict',
                    'label' => 'RESTRICT',
                ],
                [
                    'name' => 'SET_NULL',
                    'value' => 'set_null',
                    'label' => 'SET_NULL',
                ],
                [
                    'name' => 'NO_ACTION',
                    'value' => 'no_action',
                    'label' => 'NO_ACTION',
                ],
            ],
            ForeignKeyAction::toArrayList()
        );
    }
}