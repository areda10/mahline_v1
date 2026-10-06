<?php

declare(strict_types=1);

namespace Tests\Framework\Domains\Cooperatives\Factories;

use App\Domains\Cooperatives\Models\CooperativeSettings;
use Database\Factories\CooperativeSettingsFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

final class CooperativeSettingsFactoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_cooperative_settings_factory_creates_settings(): void
    {
        $settings = CooperativeSettings::factory()->create();

        $this->assertInstanceOf(
            CooperativeSettings::class,
            $settings,
        );

        $this->assertDatabaseHas('cooperative_settings', [
            'id' => $settings->id,
        ]);
    }

    public function test_cooperative_settings_factory_is_bound_to_model(): void
    {
        $factory = CooperativeSettingsFactory::new();

        $this->assertSame(
            CooperativeSettings::class,
            $factory->modelName(),
        );
    }

    public function test_factory_generates_required_attributes(): void
    {
        $settings = CooperativeSettings::factory()->make();

        $this->assertNotEmpty($settings->cooperative_id);
        $this->assertNotEmpty($settings->currency);
        $this->assertNotEmpty($settings->locale);
        $this->assertNotEmpty($settings->timezone);
        $this->assertNotEmpty($settings->order_prefix);
        $this->assertNotEmpty($settings->invoice_prefix);
    }

    public function test_factory_generates_valid_cooperative_ulid(): void
    {
        $settings = CooperativeSettings::factory()->make();

        $this->assertTrue(
            Str::isUlid($settings->cooperative_id),
        );
    }

    public function test_factory_uses_expected_defaults(): void
    {
        $settings = CooperativeSettings::factory()->make();

        $this->assertSame(
            'MAD',
            $settings->currency,
        );

        $this->assertSame(
            'fr',
            $settings->locale,
        );

        $this->assertSame(
            'Africa/Casablanca',
            $settings->timezone,
        );

        $this->assertSame(
            'CMD',
            $settings->order_prefix,
        );

        $this->assertSame(
            'FAC',
            $settings->invoice_prefix,
        );

        $this->assertSame(
            '0.00',
            $settings->default_tax_rate,
        );

        $this->assertFalse(
            $settings->prices_include_tax,
        );

        $this->assertTrue(
            $settings->notify_new_order,
        );

        $this->assertTrue(
            $settings->notify_order_status,
        );

        $this->assertTrue(
            $settings->notify_low_stock,
        );
    }

    public function test_factory_can_enable_tax_included_prices(): void
    {
        $settings = CooperativeSettings::factory()
            ->pricesIncludeTax()
            ->make();

        $this->assertTrue(
            $settings->prices_include_tax,
        );
    }

    public function test_factory_can_disable_tax_included_prices(): void
    {
        $settings = CooperativeSettings::factory()
            ->pricesExcludeTax()
            ->make();

        $this->assertFalse(
            $settings->prices_include_tax,
        );
    }

    public function test_factory_can_disable_all_notifications(): void
    {
        $settings = CooperativeSettings::factory()
            ->notificationsDisabled()
            ->make();

        $this->assertFalse(
            $settings->notify_new_order,
        );

        $this->assertFalse(
            $settings->notify_order_status,
        );

        $this->assertFalse(
            $settings->notify_low_stock,
        );
    }
}
