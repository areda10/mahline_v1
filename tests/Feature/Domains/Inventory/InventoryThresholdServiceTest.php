<?php

declare(strict_types=1);

namespace Tests\Feature\Domains\Inventory;

use App\Domains\Cooperatives\Models\Cooperative;
use App\Domains\Identity\Authorization\Models\Role;
use App\Domains\Identity\Users\Models\User;
use App\Domains\Identity\Enums\UserStatus;
use App\Domains\Inventory\Contracts\InventoryThresholdManager;
use App\Domains\Inventory\Enums\InventoryStockableType;
use App\Domains\Inventory\Models\InventoryAlertEpisode;
use App\Domains\Inventory\Models\InventoryOutboxMessage;
use App\Domains\Inventory\Models\InventoryStock;
use App\Domains\Inventory\Models\InventoryThresholdHistory;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

final class InventoryThresholdServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([RoleSeeder::class, PermissionSeeder::class, RolePermissionSeeder::class]);
    }

    public function test_low_stock_and_out_of_stock_alerts_rearm_as_separate_episodes(): void
    {
        $cooperative = Cooperative::factory()->active()->create();
        $admin = $this->userWithRole('cooperative_admin', [
            'cooperative_id' => $cooperative->getKey(),
        ]);
        $stock = InventoryStock::query()->create([
            'cooperative_id' => (string) $cooperative->getKey(),
            'stockable_type' => InventoryStockableType::PRODUCT->value,
            'stockable_id' => (string) Str::ulid(),
            'unit_code' => 'kg',
            'quantity_on_hand' => '10.000',
        ]);

        app(InventoryThresholdManager::class)->setLowStockThreshold(
            (string) $stock->getKey(),
            '5.000',
            'Seuil minimal de réapprovisionnement.',
            (string) $admin->getKey(),
            'threshold-set-001',
        );

        $stock->quantity_on_hand = '5.000';
        $stock->save();

        $this->assertSame(1, InventoryAlertEpisode::query()
            ->where('stock_id', $stock->getKey())
            ->where('alert_type', 'low_stock')
            ->whereNull('cleared_at')
            ->count());

        $stock->quantity_on_hand = '6.000';
        $stock->save();
        $stock->quantity_on_hand = '4.000';
        $stock->save();

        $this->assertSame(2, InventoryAlertEpisode::query()
            ->where('stock_id', $stock->getKey())
            ->where('alert_type', 'low_stock')
            ->count());

        $stock->quantity_on_hand = '0.000';
        $stock->save();

        $this->assertSame(1, InventoryAlertEpisode::query()
            ->where('stock_id', $stock->getKey())
            ->where('alert_type', 'out_of_stock')
            ->whereNull('cleared_at')
            ->count());
        $this->assertSame(1, InventoryThresholdHistory::query()->where('stock_id', $stock->getKey())->count());
        $this->assertGreaterThanOrEqual(3, InventoryOutboxMessage::query()
            ->where('event_key', 'inventory.stock_alert.triggered')
            ->count());
    }

    private function userWithRole(string $roleSlug, array $attributes = []): User
    {
        $role = Role::query()->where('slug', $roleSlug)->firstOrFail();

        $user = User::factory()->create(array_merge([
            'role_id' => $role->getKey(),
            'status' => UserStatus::Active,
            'email_verified_at' => now(),
        ], $attributes));
        $user->roles()->attach($role->getKey());

        return $user;
    }
}
