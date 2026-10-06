<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domains\Cooperatives\Models\CooperativeSettings;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<CooperativeSettings>
 */
final class CooperativeSettingsFactory extends Factory
{
    protected $model = CooperativeSettings::class;

    public function definition(): array
    {
        return [
            'cooperative_id' => (string) Str::ulid(),
            'currency' => 'MAD',
            'locale' => 'fr',
            'timezone' => 'Africa/Casablanca',
            'order_prefix' => 'CMD',
            'invoice_prefix' => 'FAC',
            'default_tax_rate' => 0,
            'prices_include_tax' => false,
            'notify_new_order' => true,
            'notify_order_status' => true,
            'notify_low_stock' => true,
        ];
    }

    public function pricesIncludeTax(): static
    {
        return $this->state(fn (): array => [
            'prices_include_tax' => true,
        ]);
    }

    public function pricesExcludeTax(): static
    {
        return $this->state(fn (): array => [
            'prices_include_tax' => false,
        ]);
    }

    public function notificationsDisabled(): static
    {
        return $this->state(fn (): array => [
            'notify_new_order' => false,
            'notify_order_status' => false,
            'notify_low_stock' => false,
        ]);
    }
}