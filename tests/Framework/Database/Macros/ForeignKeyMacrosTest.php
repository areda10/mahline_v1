<?php

declare(strict_types=1);

namespace Tests\Framework\Database\Macros;

use App\Core\Database\Macros\ForeignKeyMacros;
use App\Core\Enums\Database\ForeignKeyAction;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

final class ForeignKeyMacrosTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        ForeignKeyMacros::register();
    }

    public function test_registers_foreign_key_macro(): void
    {
        $this->assertTrue(
            Blueprint::hasMacro('foreignKey')
        );
    }

    public function test_foreign_key_macro_creates_foreign_key_with_cascade_delete(): void
    {
        Schema::create('foreign_key_parent_test', function (Blueprint $table): void {
            $table->ulid('id')->primary();
        });

        Schema::create('foreign_key_child_test', function (Blueprint $table): void {
            $table->ulid('id')->primary();

            $table->ulid('user_id');

            $table->foreignKey(
                'user_id',
                'foreign_key_parent_test',
                'id',
                ForeignKeyAction::CASCADE,
            );
        });

        $this->assertTrue(
            Schema::hasColumn('foreign_key_child_test', 'user_id')
        );
    }

    public function test_foreign_key_macro_supports_restrict_delete(): void
    {
        Schema::create('foreign_key_restrict_parent_test', function (Blueprint $table): void {
            $table->ulid('id')->primary();
        });

        Schema::create('foreign_key_restrict_child_test', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->ulid('parent_id');

            $table->foreignKey(
                'parent_id',
                'foreign_key_restrict_parent_test',
                'id',
                ForeignKeyAction::RESTRICT,
            );
        });

        $this->assertTrue(
            Schema::hasColumn('foreign_key_restrict_child_test', 'parent_id')
        );
    }

    public function test_foreign_key_macro_supports_set_null_delete(): void
    {
        Schema::create('foreign_key_set_null_parent_test', function (Blueprint $table): void {
            $table->ulid('id')->primary();
        });

        Schema::create('foreign_key_set_null_child_test', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->ulid('parent_id')->nullable();

            $table->foreignKey(
                'parent_id',
                'foreign_key_set_null_parent_test',
                'id',
                ForeignKeyAction::SET_NULL,
            );
        });

        $this->assertTrue(
            Schema::hasColumn('foreign_key_set_null_child_test', 'parent_id')
        );
    }

    public function test_foreign_key_macro_supports_no_action_delete(): void
    {
        Schema::create('foreign_key_no_action_parent_test', function (Blueprint $table): void {
            $table->ulid('id')->primary();
        });

        Schema::create('foreign_key_no_action_child_test', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->ulid('parent_id');

            $table->foreignKey(
                'parent_id',
                'foreign_key_no_action_parent_test',
                'id',
                ForeignKeyAction::NO_ACTION,
            );
        });

        $this->assertTrue(
            Schema::hasColumn('foreign_key_no_action_child_test', 'parent_id')
        );
    }

    public function test_foreign_key_macro_supports_on_update_action(): void
    {
        Schema::create('foreign_key_update_parent_test', function (Blueprint $table): void {
            $table->ulid('id')->primary();
        });

        Schema::create('foreign_key_update_child_test', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->ulid('parent_id');

            $table->foreignKey(
                'parent_id',
                'foreign_key_update_parent_test',
                'id',
                ForeignKeyAction::CASCADE,
                ForeignKeyAction::CASCADE,
            );
        });

        $this->assertTrue(
            Schema::hasColumn('foreign_key_update_child_test', 'parent_id')
        );
    }

    public function test_register_is_idempotent(): void
    {
        ForeignKeyMacros::register();
        ForeignKeyMacros::register();

        $this->assertTrue(
            Blueprint::hasMacro('foreignKey')
        );
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('foreign_key_child_test');
        Schema::dropIfExists('foreign_key_parent_test');

        Schema::dropIfExists('foreign_key_restrict_child_test');
        Schema::dropIfExists('foreign_key_restrict_parent_test');

        Schema::dropIfExists('foreign_key_set_null_child_test');
        Schema::dropIfExists('foreign_key_set_null_parent_test');

        Schema::dropIfExists('foreign_key_no_action_child_test');
        Schema::dropIfExists('foreign_key_no_action_parent_test');

        Schema::dropIfExists('foreign_key_update_child_test');
        Schema::dropIfExists('foreign_key_update_parent_test');

        parent::tearDown();
    }
}