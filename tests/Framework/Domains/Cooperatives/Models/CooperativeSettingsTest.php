<?php

declare(strict_types=1);

namespace Tests\Framework\Domains\Cooperatives\Models;

use App\Domains\Cooperatives\Enums\CooperativeStatus;
use App\Domains\Cooperatives\Models\Cooperative;
use App\Domains\Cooperatives\Models\CooperativeSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class CooperativeSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_cooperative_settings_extends_base_model(): void
    {
        $settings = new CooperativeSettings();

        self::assertInstanceOf(
            \App\Core\Foundation\Models\BaseModel::class,
            $settings,
        );
    }

    public function test_cooperative_settings_uses_soft_deletes(): void
    {
        $settings = new CooperativeSettings();

        self::assertContains(
            \Illuminate\Database\Eloquent\SoftDeletes::class,
            class_uses_recursive($settings),
        );
    }

    public function test_cooperative_settings_uses_expected_table(): void
    {
        $settings = new CooperativeSettings();

        self::assertSame(
            'cooperative_settings',
            $settings->getTable(),
        );
    }

    public function test_cooperative_settings_uses_ulid_as_primary_key(): void
    {
        $settings = new CooperativeSettings();

        self::assertSame('id', $settings->getKeyName());
        self::assertSame('string', $settings->getKeyType());
        self::assertFalse($settings->getIncrementing());
    }

    public function test_cooperative_settings_has_expected_fillable_attributes(): void
    {
        $settings = new CooperativeSettings();

        self::assertSame(
            [
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
            $settings->getFillable(),
        );
    }

    public function test_boolean_attributes_are_cast_to_boolean(): void
    {
        $settings = new CooperativeSettings();

        self::assertSame(
            'boolean',
            $settings->getCasts()['prices_include_tax'],
        );

        self::assertSame(
            'boolean',
            $settings->getCasts()['notify_new_order'],
        );

        self::assertSame(
            'boolean',
            $settings->getCasts()['notify_order_status'],
        );

        self::assertSame(
            'boolean',
            $settings->getCasts()['notify_low_stock'],
        );
    }

    public function test_default_tax_rate_is_cast_to_decimal(): void
    {
        $settings = new CooperativeSettings();

        self::assertSame(
            'decimal:2',
            $settings->getCasts()['default_tax_rate'],
        );
    }

    public function test_deleted_at_is_cast_to_datetime(): void
    {
        $settings = new CooperativeSettings();

        self::assertSame(
            'datetime',
            $settings->getCasts()['deleted_at'],
        );
    }

    public function test_cooperative_settings_can_be_instantiated_with_attributes(): void
    {
        $cooperative = Cooperative::factory()->create();

        $settings = new CooperativeSettings([
            'cooperative_id' => $cooperative->id,
            'currency' => 'MAD',
            'locale' => 'fr',
            'timezone' => 'Africa/Casablanca',
            'order_prefix' => 'CMD',
            'invoice_prefix' => 'FAC',
            'default_tax_rate' => 20,
            'prices_include_tax' => true,
            'notify_new_order' => false,
            'notify_order_status' => true,
            'notify_low_stock' => false,
        ]);

        self::assertSame($cooperative->id, $settings->cooperative_id);
        self::assertSame('MAD', $settings->currency);
        self::assertSame('fr', $settings->locale);
        self::assertSame('Africa/Casablanca', $settings->timezone);
        self::assertSame('CMD', $settings->order_prefix);
        self::assertSame('FAC', $settings->invoice_prefix);
        self::assertSame('20.00', $settings->default_tax_rate);
        self::assertTrue($settings->prices_include_tax);
        self::assertFalse($settings->notify_new_order);
        self::assertTrue($settings->notify_order_status);
        self::assertFalse($settings->notify_low_stock);
    }
}