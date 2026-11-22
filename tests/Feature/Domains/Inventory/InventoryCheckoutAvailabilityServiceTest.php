<?php

declare(strict_types=1);

namespace Tests\Feature\Domains\Inventory;

use App\Domains\Cooperatives\Models\Cooperative;
use App\Domains\Inventory\DTOs\InventoryCheckoutLineData;
use App\Domains\Inventory\Enums\InventoryStockableType;
use App\Domains\Inventory\Models\InventoryStock;
use App\Domains\Inventory\Services\Reservations\InventoryCheckoutAvailabilityService;
use App\Domains\Products\Models\Product;
use App\Domains\Products\Models\ProductUnit;
use Database\Seeders\ProductUnitSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

final class InventoryCheckoutAvailabilityServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ProductUnitSeeder::class);
    }

    public function test_checkout_availability_excludes_quarantined_stock_unless_authorized_and_excludes_active_count_locks(): void
    {
        $cooperative = Cooperative::factory()->active()->create();
        $unitId = ProductUnit::query()->where('code', 'kg')->value('id');
        $product = Product::factory()->forCooperative($cooperative)->create([
            'price' => '20.00',
            'currency' => 'MAD',
            'unit_id' => $unitId,
            'is_active' => true,
        ]);
        $stock = InventoryStock::query()->create([
            'cooperative_id' => (string) $cooperative->getKey(),
            'stockable_type' => 'product',
            'stockable_id' => (string) $product->getKey(),
            'unit_code' => 'kg',
            'quantity_on_hand' => '10.000',
            'quantity_quarantined' => '10.000',
            'quantity_quarantine_authorized' => '0.000',
            'quantity_reserved_normal' => '0.000',
            'quantity_reserved_quarantine' => '0.000',
        ]);

        $line = new InventoryCheckoutLineData(
            lineKey: 'cart-line-1',
            cooperativeId: (string) $cooperative->getKey(),
            stockableType: InventoryStockableType::PRODUCT,
            stockableId: (string) $product->getKey(),
            quantity: '1.000',
            unitCode: 'kg',
        );
        $service = app(InventoryCheckoutAvailabilityService::class);

        $withoutAuthorization = $service->preview([$line]);
        $this->assertSame([(string) $cooperative->getKey()], $withoutAuthorization->unavailableCooperativeIds());

        $stock->forceFill(['quantity_quarantine_authorized' => '2.000'])->save();
        $authorized = $service->preview([$line]);
        $this->assertSame([], $authorized->unavailableCooperativeIds());

        $stock->forceFill(['active_count_session_id' => (string) Str::ulid()])->save();
        $lockedByCount = $service->preview([$line]);
        $this->assertSame([(string) $cooperative->getKey()], $lockedByCount->unavailableCooperativeIds());
    }
}
