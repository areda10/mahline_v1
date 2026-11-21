<?php

declare(strict_types=1);

namespace Tests\Feature\Domains\Sales\Services;

use App\Domains\Cooperatives\Models\Cooperative;
use App\Domains\Products\Models\Product;
use App\Domains\Products\Models\ProductUnit;
use App\Domains\Sales\Contracts\SalesCartManager;
use App\Domains\Sales\Exceptions\SalesCartVersionConflict;
use App\Domains\Sales\Models\SalesCart;
use Database\Seeders\ProductUnitSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

final class SalesCartServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ProductUnitSeeder::class);
    }

    public function test_guest_cart_persists_only_a_hash_of_its_secret_token_and_expires_after_thirty_days(): void
    {
        $result = app(SalesCartManager::class)->createGuestCart();
        $cart = $result['cart'];

        $this->assertNotSame($result['guest_token'], $cart->guest_token_hash);
        $this->assertSame(hash('sha256', $result['guest_token']), $cart->guest_token_hash);
        $this->assertEqualsWithDelta(now()->addDays(30)->timestamp, $cart->expires_at->timestamp, 1);
        $this->assertNull($cart->customer_id);
    }

    public function test_adding_same_commercial_line_increments_quantity_and_cart_version(): void
    {
        [$product] = $this->product();
        $manager = app(SalesCartManager::class);
        $cart = $manager->getOrCreateCustomerCart((string) Str::ulid());

        $first = $manager->addItem((string) $cart->getKey(), (string) $product->getKey(), null, '2', 1);
        $second = $manager->addItem((string) $cart->getKey(), (string) $product->getKey(), null, '3.5', 2);

        $this->assertSame('5.500', $second->quantity);
        $this->assertSame(3, $second->cart->fresh()->version);
        $this->assertSame($first->getKey(), $second->getKey());
    }

    public function test_quantity_supports_exactly_three_decimal_places(): void
    {
        [$product] = $this->product();
        $manager = app(SalesCartManager::class);
        $cart = $manager->getOrCreateCustomerCart((string) Str::ulid());

        $item = $manager->addItem((string) $cart->getKey(), (string) $product->getKey(), null, '0.125', 1);

        $this->assertSame('0.125', $item->quantity);

        try {
            $manager->changeQuantity((string) $cart->getKey(), (string) $item->getKey(), '0.0001', 2);
            $this->fail('A quantity with more than three decimal places must be rejected.');
        } catch (ValidationException) {
            $this->assertSame('0.125', $item->fresh()->quantity);
        }
    }

    public function test_refresh_marks_disabled_product_unavailable_and_reactivates_it_without_losing_the_line(): void
    {
        [$product] = $this->product();
        $manager = app(SalesCartManager::class);
        $cart = $manager->getOrCreateCustomerCart((string) Str::ulid());
        $item = $manager->addItem((string) $cart->getKey(), (string) $product->getKey(), null, '1', 1);

        $product->forceFill(['is_active' => false])->save();
        $cart = $manager->refresh((string) $cart->getKey());

        $this->assertSame('product_unavailable', $cart->activeItems->firstOrFail()->unavailable_reason);

        $product->forceFill(['is_active' => true])->save();
        $cart = $manager->refresh((string) $cart->getKey());

        $this->assertSame($item->getKey(), $cart->activeItems->firstOrFail()->getKey());
        $this->assertNull($cart->activeItems->firstOrFail()->unavailable_reason);
    }

    public function test_price_increase_requires_explicit_acceptance_and_price_decrease_is_applied_automatically(): void
    {
        [$product] = $this->product();
        $manager = app(SalesCartManager::class);
        $cart = $manager->getOrCreateCustomerCart((string) Str::ulid());
        $item = $manager->addItem((string) $cart->getKey(), (string) $product->getKey(), null, '1', 1);

        $product->forceFill(['price' => '25.90'])->save();
        $cart = $manager->refresh((string) $cart->getKey());
        $item->refresh();

        $this->assertSame('price_change_pending', $item->unavailable_reason);
        $this->assertSame('25.90', $item->current_unit_price);
        $this->assertSame('19.90', $item->accepted_unit_price);

        $manager->acceptCurrentChanges(
            (string) $cart->getKey(),
            (string) $item->getKey(),
            (int) $cart->version,
        );
        $this->assertNull($item->fresh()->unavailable_reason);
        $this->assertSame('25.90', $item->fresh()->accepted_unit_price);

        $product->forceFill(['price' => '15.00'])->save();
        $cart = $manager->refresh((string) $cart->getKey());

        $this->assertNull($item->fresh()->unavailable_reason);
        $this->assertSame('15.00', $item->fresh()->accepted_unit_price);
    }

    public function test_quantity_above_one_hundred_is_rejected(): void
    {
        [$product] = $this->product();
        $manager = app(SalesCartManager::class);
        $cart = $manager->getOrCreateCustomerCart((string) Str::ulid());

        $this->expectException(ValidationException::class);
        $manager->addItem((string) $cart->getKey(), (string) $product->getKey(), null, '100.001', 1);
    }

    public function test_stale_cart_version_is_rejected_without_overwriting_newer_state(): void
    {
        [$product] = $this->product();
        $manager = app(SalesCartManager::class);
        $cart = $manager->getOrCreateCustomerCart((string) Str::ulid());
        $manager->addItem((string) $cart->getKey(), (string) $product->getKey(), null, '1', 1);

        try {
            $manager->addItem((string) $cart->getKey(), (string) $product->getKey(), null, '1', 1);
            $this->fail('A stale cart version should be rejected.');
        } catch (SalesCartVersionConflict $exception) {
            $this->assertSame(2, $exception->currentVersion);
        }

        $this->assertSame('1.000', $cart->activeItems()->firstOrFail()->quantity);
    }

    public function test_guest_cart_merge_adds_matching_lines_and_preserves_guest_history(): void
    {
        [$product] = $this->product();
        $manager = app(SalesCartManager::class);
        $guest = $manager->createGuestCart();
        $customerId = (string) Str::ulid();
        $customerCart = $manager->getOrCreateCustomerCart($customerId);

        $manager->addItem((string) $guest['cart']->getKey(), (string) $product->getKey(), null, '2', 1);
        $manager->addItem((string) $customerCart->getKey(), (string) $product->getKey(), null, '3', 1);

        $merged = $manager->mergeGuestCart($guest['guest_token'], $customerId);

        $this->assertSame('5.000', $merged->activeItems()->firstOrFail()->quantity);
        $this->assertNotNull(SalesCart::query()->findOrFail($guest['cart']->getKey())->merged_into_cart_id);
        $this->assertSame(0, SalesCart::query()->findOrFail($guest['cart']->getKey())->activeItems()->count());
    }

    public function test_removing_a_line_keeps_a_logical_history_record(): void
    {
        [$product] = $this->product();
        $manager = app(SalesCartManager::class);
        $cart = $manager->getOrCreateCustomerCart((string) Str::ulid());
        $item = $manager->addItem((string) $cart->getKey(), (string) $product->getKey(), null, '1', 1);

        $manager->removeItem((string) $cart->getKey(), (string) $item->getKey(), 2);

        $item->refresh();
        $this->assertSame('removed', $item->status);
        $this->assertNotNull($item->removed_at);
        $this->assertDatabaseHas('sales_cart_items', ['id' => $item->getKey(), 'status' => 'removed']);
    }

    /** @return array{Product, Cooperative} */
    private function product(): array
    {
        $cooperative = Cooperative::factory()->active()->create();
        $unitId = ProductUnit::query()->where('code', 'u')->value('id');
        $product = Product::factory()->forCooperative($cooperative)->create([
            'price' => '19.90',
            'currency' => 'MAD',
            'unit_id' => $unitId,
            'is_active' => true,
        ]);

        return [$product, $cooperative];
    }
}
