<?php

declare(strict_types=1);

namespace Tests\Framework\Database\Migrations;

use App\Domains\Cooperatives\Models\Cooperative;
use App\Domains\Countries\Models\Country;
use App\Domains\Localization\Models\Address;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

final class CooperativeSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_cooperative_settings_table_exists(): void
    {
        $this->assertTrue(
            Schema::hasTable('cooperative_settings'),
        );
    }

    public function test_cooperative_settings_table_has_required_columns(): void
    {
        $this->assertTrue(
            Schema::hasColumns(
                'cooperative_settings',
                [
                    'id',
                    'cooperative_id',
                    'currency',
                    'locale',
                    'timezone',
                    'order_prefix',
                    'invoice_prefix',
                    'default_tax_rate',
                    'prices_include_tax',
                    'notify_new_order',
                    'notify_order_status',
                    'notify_low_stock',
                ],
            ),
        );
    }

    public function test_cooperative_settings_table_has_audit_columns(): void
    {
        $this->assertTrue(
            Schema::hasColumns(
                'cooperative_settings',
                [
                    'created_by',
                    'updated_by',
                    'deleted_by',
                ],
            ),
        );
    }

    public function test_cooperative_settings_table_has_timestamps(): void
    {
        $this->assertTrue(
            Schema::hasColumns(
                'cooperative_settings',
                [
                    'created_at',
                    'updated_at',
                ],
            ),
        );
    }

    public function test_cooperative_settings_table_has_soft_delete_column(): void
    {
        $this->assertTrue(
            Schema::hasColumn(
                'cooperative_settings',
                'deleted_at',
            ),
        );
    }

    public function test_cooperative_id_is_unique(): void
    {
        $indexes = Schema::getIndexes('cooperative_settings');

        $cooperativeIdIndex = collect($indexes)->first(
            fn (array $index): bool =>
                $index['name'] === 'cooperative_settings_cooperative_id_unique',
        );

        $this->assertNotNull($cooperativeIdIndex);
        $this->assertTrue($cooperativeIdIndex['unique']);
        $this->assertSame(
            ['cooperative_id'],
            $cooperativeIdIndex['columns'],
        );
    }

    public function test_expected_indexes_exist(): void
    {
        $indexes = Schema::getIndexes('cooperative_settings');

        $indexNames = collect($indexes)
            ->pluck('name')
            ->all();

        $this->assertContains(
            'cooperative_settings_currency_index',
            $indexNames,
        );

        $this->assertContains(
            'cooperative_settings_locale_index',
            $indexNames,
        );

        $this->assertContains(
            'cooperative_settings_timezone_index',
            $indexNames,
        );
    }


    public function test_default_values_are_correct(): void
    {
        $country = Country::query()->firstOrCreate(
            ['code' => 'MA'],
            [
                'iso3' => 'MAR',
                'name' => 'Morocco',
                'native_name' => 'المغرب',
                'phone_code' => '+212',
                'currency' => 'MAD',
                'locale' => 'fr',
                'is_active' => true,
            ],
        );

        $address = Address::factory()->create([
            'country_id' => $country->id,
        ]);

        $cooperative = Cooperative::factory()->create([
            'address_id' => $address->id,
        ]);

        $now = now();

        DB::table('cooperative_settings')->insert([
            'id' => (string) Str::ulid(),
            'cooperative_id' => $cooperative->id,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $settings = DB::table('cooperative_settings')
            ->where('cooperative_id', $cooperative->id)
            ->first();

        $this->assertNotNull($settings);

        $this->assertSame('MAD', $settings->currency);
        $this->assertSame('fr', $settings->locale);
        $this->assertSame('Africa/Casablanca', $settings->timezone);
        $this->assertSame('CMD', $settings->order_prefix);
        $this->assertSame('FAC', $settings->invoice_prefix);
        $this->assertEquals(0, $settings->default_tax_rate);
        $this->assertFalse((bool) $settings->prices_include_tax);
        $this->assertTrue((bool) $settings->notify_new_order);
        $this->assertTrue((bool) $settings->notify_order_status);
        $this->assertTrue((bool) $settings->notify_low_stock);
    }

}
