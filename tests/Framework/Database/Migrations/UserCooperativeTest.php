<?php

declare(strict_types=1);

namespace Tests\Framework\Database\Migrations;

use App\Domains\Cooperatives\Models\Cooperative;
use App\Domains\Identity\Users\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

final class UserCooperativeTest extends TestCase
{
    use RefreshDatabase;

    public function test_users_table_has_cooperative_id_column(): void
    {
        self::assertTrue(
            Schema::hasColumn('users', 'cooperative_id'),
        );
    }

    public function test_cooperative_id_is_nullable(): void
    {
        $cooperativeId = collect( 
            Schema::getColumns('users'), 
            )->firstWhere('name', 'cooperative_id'); 
            
            self::assertNotNull($cooperativeId); 
            
            self::assertTrue( 
                $cooperativeId['nullable'], 
            );
    }

    public function test_cooperative_id_has_expected_index(): void
    {
        $indexes = collect(
            Schema::getIndexes('users'),
        );

        self::assertTrue(
            $indexes->contains(
                fn (array $index): bool =>
                    $index['name'] === 'users_cooperative_id_foreign',
            ),
        );
    }

    public function test_cooperative_id_is_foreign_key_to_cooperatives(): void
    {
        $foreignKeys = Schema::getForeignKeys('users');

        self::assertTrue(
            collect($foreignKeys)->contains(
                fn (array $foreignKey): bool =>
                    $foreignKey['name'] === 'users_cooperative_id_foreign'
                    && $foreignKey['columns'] === ['cooperative_id']
                    && $foreignKey['foreign_table'] === 'cooperatives'
                    && $foreignKey['foreign_columns'] === ['id'],
            ),
        );
    }

    public function test_user_can_have_null_cooperative_id(): void
    {
        $user = User::factory()->create([
            'cooperative_id' => null,
        ]);

        self::assertNull($user->cooperative_id);
    }

    public function test_user_can_reference_an_existing_cooperative(): void
    {
        $cooperative = Cooperative::factory()->create();

        $user = User::factory()->create([
            'cooperative_id' => $cooperative->id,
        ]);

        self::assertSame(
            $cooperative->id,
            $user->cooperative_id,
        );
    }

    public function test_user_cannot_reference_non_existing_cooperative(): void
    {
        $this->expectException(QueryException::class);

        User::factory()->create([
            'cooperative_id' => (string) \Illuminate\Support\Str::ulid(),
        ]);
    }
}