<?php

declare(strict_types=1);

namespace Tests\Feature\Domains\Inventory;

use App\Domains\Cooperatives\Models\Cooperative;
use App\Domains\Identity\Authorization\Models\Role;
use App\Domains\Identity\Users\Models\User;
use App\Domains\Identity\Enums\UserStatus;
use App\Domains\Inventory\Contracts\InventoryStockManager;
use App\Domains\Inventory\Enums\InventoryStockableType;
use App\Domains\Inventory\Models\InventoryMovement;
use App\Domains\Inventory\Models\InventoryStock;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
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
        $productId = (string) Str::ulid();
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
