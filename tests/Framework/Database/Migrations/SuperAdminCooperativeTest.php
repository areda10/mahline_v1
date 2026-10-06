<?php

declare(strict_types=1);

namespace Tests\Framework\Database\Migrations;

use App\Domains\Cooperatives\Models\Cooperative;
use App\Domains\Countries\Models\Country;
use App\Domains\Identity\Users\Models\User;
use App\Domains\Localization\Models\Address;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

final class SuperAdminCooperativeTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_cooperative_table_exists(): void
    {
        self::assertTrue(
            Schema::hasTable('super_admin_cooperative'),
        );
    }

    public function test_super_admin_cooperative_table_has_expected_columns(): void
    {
        self::assertTrue(
            Schema::hasColumns(
                'super_admin_cooperative',
                [
                    'id',
                    'super_admin_id',
                    'cooperative_id',
                    'created_at',
                    'updated_at',
                ],
            ),
        );
    }

    // public function test_super_admin_cooperative_uses_ulid_primary_key(): void
    // {
    //     $columns = Schema::getColumns('super_admin_cooperative');

    //     $id = collect($columns)
    //         ->firstWhere('name', 'id');

    //     self::assertNotNull($id);
    //     self::assertSame('char', $id['type_name']);
    //     self::assertSame(26, $id['length']);
    // }

    public function test_super_admin_cooperative_uses_ulid_primary_key(): void
    {
        $columns = Schema::getColumns('super_admin_cooperative');

        $id = collect($columns)
            ->firstWhere('name', 'id');

        self::assertNotNull($id);

        self::assertSame(
            'char',
            $id['type_name'],
        );

        self::assertStringContainsString(
            '26',
            $id['type'],
        );
    }

    public function test_super_admin_id_has_foreign_key_to_users(): void
    {
        $foreignKeys = Schema::getForeignKeys(
            'super_admin_cooperative',
        );

        $foreignKey = collect($foreignKeys)
            ->first(
                fn (array $key): bool =>
                    $key['columns'] === ['super_admin_id'],
            );

        self::assertNotNull($foreignKey);

        self::assertSame(
            'users',
            $foreignKey['foreign_table'],
        );

        self::assertSame(
            ['id'],
            $foreignKey['foreign_columns'],
        );
    }

    public function test_cooperative_id_has_foreign_key_to_cooperatives(): void
    {
        $foreignKeys = Schema::getForeignKeys(
            'super_admin_cooperative',
        );

        $foreignKey = collect($foreignKeys)
            ->first(
                fn (array $key): bool =>
                    $key['columns'] === ['cooperative_id'],
            );

        self::assertNotNull($foreignKey);

        self::assertSame(
            'cooperatives',
            $foreignKey['foreign_table'],
        );

        self::assertSame(
            ['id'],
            $foreignKey['foreign_columns'],
        );
    }

    public function test_super_admin_cooperative_pair_is_unique(): void
    {
        $indexes = Schema::getIndexes(
            'super_admin_cooperative',
        );

        $uniqueIndex = collect($indexes)
            ->first(
                fn (array $index): bool =>
                    $index['unique'] === true
                    && $index['columns'] === [
                        'super_admin_id',
                        'cooperative_id',
                    ],
            );

        self::assertNotNull($uniqueIndex);
    }

    public function test_super_admin_cooperative_pair_can_be_created(): void
    {
        $user = User::factory()->create();

        $cooperative = Cooperative::factory()->create();

        $pivotId = (string) str()->ulid();

        \DB::table('super_admin_cooperative')->insert([
            'id' => $pivotId,
            'super_admin_id' => $user->id,
            'cooperative_id' => $cooperative->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        self::assertDatabaseHas(
            'super_admin_cooperative',
            [
                'id' => $pivotId,
                'super_admin_id' => $user->id,
                'cooperative_id' => $cooperative->id,
            ],
        );
    }

    public function test_super_admin_cooperative_pair_cannot_be_duplicated(): void
    {
        $user = User::factory()->create();

        $cooperative = Cooperative::factory()->create();

        $attributes = [
            'id' => (string) str()->ulid(),
            'super_admin_id' => $user->id,
            'cooperative_id' => $cooperative->id,
            'created_at' => now(),
            'updated_at' => now(),
        ];

        \DB::table('super_admin_cooperative')->insert($attributes);

        $this->expectException(
            \Illuminate\Database\QueryException::class,
        );

        \DB::table('super_admin_cooperative')->insert([
            ...$attributes,
            'id' => (string) str()->ulid(),
        ]);
    }

    public function test_super_admin_can_be_assigned_to_multiple_cooperatives(): void
    {
        $user = User::factory()->create();

        $country = Country::factory()->create();

        $addressA = Address::factory()->create([
            'country_id' => $country->id,
        ]);

        $addressB = Address::factory()->create([
            'country_id' => $country->id,
        ]);

        $cooperativeA = Cooperative::factory()->create([
            'address_id' => $addressA->id,
        ]);

        $cooperativeB = Cooperative::factory()->create([
            'address_id' => $addressB->id,
        ]);

        \DB::table('super_admin_cooperative')->insert([
            'id' => (string) str()->ulid(),
            'super_admin_id' => $user->id,
            'cooperative_id' => $cooperativeA->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        \DB::table('super_admin_cooperative')->insert([
            'id' => (string) str()->ulid(),
            'super_admin_id' => $user->id,
            'cooperative_id' => $cooperativeB->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        self::assertSame(
            2,
            \DB::table('super_admin_cooperative')
                ->where('super_admin_id', $user->id)
                ->count(),
        );
    }

}