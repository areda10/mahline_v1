<?php

declare(strict_types=1);

namespace Tests\Feature\Domains\Inventory;

use App\Domains\Cooperatives\Models\Cooperative;
use App\Domains\Identity\Authorization\Models\Role;
use App\Domains\Identity\Enums\UserStatus;
use App\Domains\Identity\Users\Models\User;
use App\Domains\Inventory\Contracts\InventoryStockManager;
use App\Domains\Inventory\Enums\InventoryStockableType;
use App\Domains\Inventory\Models\InventoryMovement;
use App\Domains\Inventory\Models\InventoryStock;
use App\Domains\Inventory\Services\Stock\InventoryStockQueryService;
use App\Domains\Products\Models\Product;
use App\Domains\Products\Models\ProductUnit;
use App\Domains\Products\Models\ProductVariant;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class InventoryStockServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([RoleSeeder::class, PermissionSeeder::class, RolePermissionSeeder::class]);
    }

    public function test_stock_can_be_tracked_from_zero_then_receive_a_positive_entry(): void
    {
        $cooperative = Cooperative::factory()->active()->create();
        $admin = $this->userWithRole('cooperative_admin', [
            'cooperative_id' => $cooperative->getKey(),
        ]);
        $unit = ProductUnit::query()->firstOrCreate(
            ['code' => 'kg'],
            ['label' => 'Kilogramme', 'dimension' => 'weight', 'sort_order' => 10, 'is_active' => true],
        );
        $product = Product::factory()->forCooperative($cooperative)->create([
            'unit_id' => $unit->getKey(),
            'price' => '10.00',
            'currency' => 'MAD',
        ]);
        $productId = (string) $product->getKey();
        $manager = app(InventoryStockManager::class);

        $stockId = $manager->initialize(
            (string) $cooperative->getKey(),
            InventoryStockableType::PRODUCT,
            $productId,
            'kg',
            '0.000',
            'Création du suivi de stock avant première réception.',
            (string) $admin->getKey(),
            'stock-init-zero-001',
        );

        $stock = InventoryStock::query()->findOrFail($stockId);
        $this->assertSame('0.000', $stock->quantity_on_hand);

        $manager->receive(
            (string) $cooperative->getKey(),
            InventoryStockableType::PRODUCT,
            $productId,
            '4.250',
            'Première réception de production.',
            (string) $admin->getKey(),
            'stock-receive-first-001',
            'production_entry',
        );

        $stock->refresh();
        $this->assertSame('4.250', $stock->quantity_on_hand);
        $this->assertSame(2, InventoryMovement::query()->where('stock_id', $stockId)->count());
    }

    public function test_parent_product_with_variants_cannot_own_a_stock_balance(): void
    {
        $cooperative = Cooperative::factory()->active()->create();
        $admin = $this->userWithRole('cooperative_admin', [
            'cooperative_id' => $cooperative->getKey(),
        ]);
        $unit = ProductUnit::query()->firstOrCreate(
            ['code' => 'kg'],
            ['label' => 'Kilogramme', 'dimension' => 'weight', 'sort_order' => 10, 'is_active' => true],
        );
        $product = Product::factory()->forCooperative($cooperative)->create([
            'unit_id' => null,
            'price' => '10.00',
            'currency' => 'MAD',
        ]);
        ProductVariant::factory()->create([
            'product_id' => $product->getKey(),
            'unit_id' => $unit->getKey(),
        ]);

        $this->expectException(\InvalidArgumentException::class);

        app(InventoryStockManager::class)->initialize(
            (string) $cooperative->getKey(),
            InventoryStockableType::PRODUCT,
            (string) $product->getKey(),
            'kg',
            '5.000',
            'Le parent ne porte pas de stock lorsque des variantes existent.',
            (string) $admin->getKey(),
            'stock-parent-with-variants-001',
        );
    }

    public function test_variant_owns_its_own_stock_balance(): void
    {
        $cooperative = Cooperative::factory()->active()->create();
        $admin = $this->userWithRole('cooperative_admin', [
            'cooperative_id' => $cooperative->getKey(),
        ]);
        $unit = ProductUnit::query()->firstOrCreate(
            ['code' => 'kg'],
            ['label' => 'Kilogramme', 'dimension' => 'weight', 'sort_order' => 10, 'is_active' => true],
        );
        $product = Product::factory()->forCooperative($cooperative)->create([
            'unit_id' => null,
            'price' => '10.00',
            'currency' => 'MAD',
        ]);
        $variant = ProductVariant::factory()->create([
            'product_id' => $product->getKey(),
            'unit_id' => $unit->getKey(),
        ]);

        $stockId = app(InventoryStockManager::class)->initialize(
            (string) $cooperative->getKey(),
            InventoryStockableType::VARIANT,
            (string) $variant->getKey(),
            'kg',
            '2.000',
            'Stock initial de la variante.',
            (string) $admin->getKey(),
            'stock-variant-init-001',
        );

        $stock = InventoryStock::query()->findOrFail($stockId);
        $this->assertSame('variant', $stock->stockable_type);
        $this->assertSame((string) $variant->getKey(), (string) $stock->stockable_id);
        $this->assertSame('2.000', $stock->quantity_on_hand);
    }

    public function test_stock_status_is_rupture_while_item_is_locked_for_physical_count(): void
    {
        $cooperative = Cooperative::factory()->active()->create();
        $user = $this->userWithRole('user', [
            'cooperative_id' => $cooperative->getKey(),
        ]);
        $stock = InventoryStock::query()->create([
            'cooperative_id' => (string) $cooperative->getKey(),
            'stockable_type' => InventoryStockableType::PRODUCT->value,
            'stockable_id' => (string) \Illuminate\Support\Str::ulid(),
            'unit_code' => 'kg',
            'quantity_on_hand' => '5.000',
            'active_count_session_id' => (string) \Illuminate\Support\Str::ulid(),
        ]);

        $status = app(InventoryStockQueryService::class)->show(
            (string) $stock->getKey(),
            (string) $user->getKey(),
        );

        $this->assertSame('Rupture de stock', $status['status']);
        $this->assertArrayNotHasKey('quantity_on_hand', $status);
    }

    private function userWithRole(string $roleSlug, array $attributes = []): User
    {
        $role = Role::query()->where('slug', $roleSlug)->firstOrFail();

        $user = User::factory()->create(array_merge([
            'status' => UserStatus::Active,
            'email_verified_at' => now(),
        ], $attributes));
        $user->roles()->attach($role->getKey());

        return $user;
    }
}
