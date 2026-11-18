<?php

declare(strict_types=1);

namespace Tests\Feature\Domains\Orders\Services;

use App\Domains\Cooperatives\Models\Cooperative;
use App\Domains\Identity\Authorization\Models\Role;
use App\Domains\Identity\Enums\UserStatus;
use App\Domains\Identity\Users\Models\User;
use App\Domains\Orders\Models\Order;
use App\Domains\Orders\Services\OrderCreationService;
use App\Domains\Products\Models\Product;
use App\Domains\Products\Models\ProductVariant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class OrderCreationServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_checkout_creates_one_order_with_cooperative_segments_and_price_snapshots(): void
    {
        $customer = $this->customer();
        $cooperativeA = Cooperative::factory()->create();
        $cooperativeB = Cooperative::factory()->create();

        $productA = Product::factory()->forCooperative($cooperativeA)->create([
            'price' => '20.00',
            'currency' => 'MAD',
        ]);
        $productB = Product::factory()->forCooperative($cooperativeB)->create([
            'price' => '10.00',
            'currency' => 'MAD',
        ]);

        $order = app(OrderCreationService::class)->createFromCheckout($customer, [
            'delivery_address_snapshot' => [
                'street' => 'Avenue Hassan II',
                'city' => 'Casablanca',
                'country' => 'Maroc',
            ],
            'items' => [
                [
                    'product_id' => (string) $productA->getKey(),
                    'quantity' => '2.500',
                    'unit' => 'piece',
                    'unit_price' => '0.01',
                ],
                [
                    'product_id' => (string) $productB->getKey(),
                    'quantity' => '1.000',
                ],
            ],
        ]);

        $this->assertCount(2, $order->cooperativeOrders);
        $this->assertSame('30.00', $order->items_subtotal);
        $this->assertSame('30.00', $order->grand_total);
        $this->assertSame('MAD', $order->currency);
        $this->assertDatabaseHas('order_items', [
            'product_id' => $productA->getKey(),
            'unit_price' => '20.00',
            'quantity' => '2.500',
            'line_total' => '50.00',
        ]);
        $this->assertDatabaseHas('order_items', [
            'product_id' => $productB->getKey(),
            'unit_price' => '10.00',
            'line_total' => '10.00',
        ]);
        $this->assertDatabaseHas('order_status_histories', [
            'subject_type' => 'order',
            'subject_id' => $order->getKey(),
            'to_status' => 'awaiting_cooperatives',
        ]);
    }

    public function test_checkout_uses_variant_override_price_and_rejects_unpriced_products(): void
    {
        $customer = $this->customer();
        $cooperative = Cooperative::factory()->create();
        $product = Product::factory()->forCooperative($cooperative)->create([
            'price' => '80.00',
            'currency' => 'MAD',
        ]);
        $variant = ProductVariant::factory()->create([
            'product_id' => $product->getKey(),
            'price' => '92.50',
        ]);

        $order = app(OrderCreationService::class)->createFromCheckout($customer, [
            'delivery_address_snapshot' => ['city' => 'Rabat', 'country' => 'Maroc'],
            'items' => [[
                'product_id' => (string) $product->getKey(),
                'product_variant_id' => (string) $variant->getKey(),
                'quantity' => '1',
            ]],
        ]);

        $this->assertSame('92.50', $order->cooperativeOrders->first()->items->first()->unit_price);

        $unpriced = Product::factory()->forCooperative($cooperative)->create([
            'price' => null,
        ]);

        $this->expectException(\Illuminate\Validation\ValidationException::class);

        app(OrderCreationService::class)->createFromCheckout($customer, [
            'delivery_address_snapshot' => ['city' => 'Rabat', 'country' => 'Maroc'],
            'items' => [[
                'product_id' => (string) $unpriced->getKey(),
                'quantity' => '1',
            ]],
        ]);
    }

    private function customer(): User
    {
        $role = Role::query()->firstOrCreate(
            ['slug' => 'customer'],
            ['name' => 'Customer', 'is_active' => true],
        );

        $customer = User::factory()->create([
            'status' => UserStatus::Active,
            'email_verified_at' => now(),
        ]);
        $customer->roles()->attach($role->getKey());

        return $customer;
    }
}
