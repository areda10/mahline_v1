<?php

declare(strict_types=1);

namespace Tests\Framework\Database\Macros;

use App\Core\Database\Macros\IndexMacros;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

final class IndexMacrosTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        IndexMacros::register();
    }

    public function test_registers_index_name_macro(): void
    {
        $this->assertTrue(
            Blueprint::hasMacro('indexName')
        );
    }

    public function test_registers_unique_name_macro(): void
    {
        $this->assertTrue(
            Blueprint::hasMacro('uniqueName')
        );
    }

    public function test_registers_index_columns_macro(): void
    {
        $this->assertTrue(
            Blueprint::hasMacro('indexColumns')
        );
    }

    public function test_registers_unique_columns_macro(): void
    {
        $this->assertTrue(
            Blueprint::hasMacro('uniqueColumns')
        );
    }

    public function test_index_name_creates_index(): void
    {
        Schema::create('index_macros_test', function (Blueprint $table): void {
            $table->id();
            $table->string('email');

            $table->indexName('email');
        });

        $indexes = Schema::getIndexes('index_macros_test');

        $this->assertTrue(
            collect($indexes)->contains(
                static fn (array $index): bool =>
                    in_array('email', $index['columns'], true)
                    && $index['unique'] === false
            )
        );
    }

    public function test_index_name_supports_custom_name(): void
    {
        Schema::create('index_macros_custom_name_test', function (Blueprint $table): void {
            $table->id();
            $table->string('email');

            $table->indexName(
                'email',
                'custom_email_index'
            );
        });

        $indexes = Schema::getIndexes('index_macros_custom_name_test');

        $this->assertTrue(
            collect($indexes)->contains(
                static fn (array $index): bool =>
                    $index['name'] === 'custom_email_index'
            )
        );
    }

    public function test_unique_name_creates_unique_index(): void
    {
        Schema::create('index_macros_unique_test', function (Blueprint $table): void {
            $table->id();
            $table->string('email');

            $table->uniqueName('email');
        });

        $indexes = Schema::getIndexes('index_macros_unique_test');

        $this->assertTrue(
            collect($indexes)->contains(
                static fn (array $index): bool =>
                    in_array('email', $index['columns'], true)
                    && $index['unique'] === true
            )
        );
    }

    public function test_unique_name_supports_custom_name(): void
    {
        Schema::create('index_macros_unique_custom_name_test', function (Blueprint $table): void {
            $table->id();
            $table->string('email');

            $table->uniqueName(
                'email',
                'custom_email_unique'
            );
        });

        $indexes = Schema::getIndexes('index_macros_unique_custom_name_test');

        $this->assertTrue(
            collect($indexes)->contains(
                static fn (array $index): bool =>
                    $index['name'] === 'custom_email_unique'
                    && $index['unique'] === true
            )
        );
    }

    public function test_index_columns_creates_composite_index(): void
    {
        Schema::create('index_macros_composite_test', function (Blueprint $table): void {
            $table->id();
            $table->string('cooperative_id');
            $table->string('status');

            $table->indexColumns([
                'cooperative_id',
                'status',
            ]);
        });

        $indexes = Schema::getIndexes('index_macros_composite_test');

        $this->assertTrue(
            collect($indexes)->contains(
                static fn (array $index): bool =>
                    $index['columns'] === [
                        'cooperative_id',
                        'status',
                    ]
                    && $index['unique'] === false
            )
        );
    }

    public function test_unique_columns_creates_composite_unique_index(): void
    {
        Schema::create('index_macros_composite_unique_test', function (Blueprint $table): void {
            $table->id();
            $table->string('cooperative_id');
            $table->string('slug');

            $table->uniqueColumns([
                'cooperative_id',
                'slug',
            ]);
        });

        $indexes = Schema::getIndexes('index_macros_composite_unique_test');

        $this->assertTrue(
            collect($indexes)->contains(
                static fn (array $index): bool =>
                    $index['columns'] === [
                        'cooperative_id',
                        'slug',
                    ]
                    && $index['unique'] === true
            )
        );
    }

    public function test_index_columns_supports_custom_name(): void
    {
        Schema::create('index_macros_composite_named_test', function (Blueprint $table): void {
            $table->id();
            $table->string('cooperative_id');
            $table->string('status');

            $table->indexColumns(
                [
                    'cooperative_id',
                    'status',
                ],
                'cooperative_status_index'
            );
        });

        $indexes = Schema::getIndexes('index_macros_composite_named_test');

        $this->assertTrue(
            collect($indexes)->contains(
                static fn (array $index): bool =>
                    $index['name'] === 'cooperative_status_index'
            )
        );
    }

    public function test_unique_columns_supports_custom_name(): void
    {
        Schema::create('index_macros_composite_unique_named_test', function (Blueprint $table): void {
            $table->id();
            $table->string('cooperative_id');
            $table->string('slug');

            $table->uniqueColumns(
                [
                    'cooperative_id',
                    'slug',
                ],
                'cooperative_slug_unique'
            );
        });

        $indexes = Schema::getIndexes('index_macros_composite_unique_named_test');

        $this->assertTrue(
            collect($indexes)->contains(
                static fn (array $index): bool =>
                    $index['name'] === 'cooperative_slug_unique'
                    && $index['unique'] === true
            )
        );
    }

    public function test_register_is_idempotent(): void
    {
        IndexMacros::register();
        IndexMacros::register();

        $this->assertTrue(
            Blueprint::hasMacro('indexName')
        );

        $this->assertTrue(
            Blueprint::hasMacro('uniqueName')
        );

        $this->assertTrue(
            Blueprint::hasMacro('indexColumns')
        );

        $this->assertTrue(
            Blueprint::hasMacro('uniqueColumns')
        );
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('index_macros_test');
        Schema::dropIfExists('index_macros_custom_name_test');
        Schema::dropIfExists('index_macros_unique_test');
        Schema::dropIfExists('index_macros_unique_custom_name_test');
        Schema::dropIfExists('index_macros_composite_test');
        Schema::dropIfExists('index_macros_composite_unique_test');
        Schema::dropIfExists('index_macros_composite_named_test');
        Schema::dropIfExists('index_macros_composite_unique_named_test');

        parent::tearDown();
    }
}