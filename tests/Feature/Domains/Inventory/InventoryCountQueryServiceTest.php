<?php

declare(strict_types=1);

namespace Tests\Feature\Domains\Inventory;

use App\Domains\Cooperatives\Models\Cooperative;
use App\Domains\Identity\Authorization\Models\Role;
use App\Domains\Identity\Enums\UserStatus;
use App\Domains\Identity\Users\Models\User;
use App\Domains\Inventory\Contracts\InventoryCountManager;
use App\Domains\Inventory\Enums\InventoryStockableType;
use App\Domains\Inventory\Models\InventoryCountItem;
use App\Domains\Inventory\Models\InventoryStock;
use App\Domains\Inventory\Services\Counts\InventoryCountQueryService;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

final class InventoryCountQueryServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([RoleSeeder::class, PermissionSeeder::class, RolePermissionSeeder::class]);
    }

    public function test_user_can_see_counted_quantity_but_not_theoretical_quantity_or_discrepancy(): void
    {
        $cooperative = Cooperative::factory()->active()->create();
        $admin = $this->userWithRole('cooperative_admin', ['cooperative_id' => $cooperative->getKey()]);
        $counter = $this->userWithRole('user', ['cooperative_id' => $cooperative->getKey()]);

        $stock = InventoryStock::query()->create([
            'cooperative_id' => (string) $cooperative->getKey(),
            'stockable_type' => InventoryStockableType::PRODUCT->value,
            'stockable_id' => (string) Str::ulid(),
            'unit_code' => 'u',
            'quantity_on_hand' => '10.000',
        ]);

        $sessionId = app(InventoryCountManager::class)->createSession(
            (string) $cooperative->getKey(),
            [(string) $stock->getKey()],
            (string) $admin->getKey(),
            'count-query-session-001',
        );
        $item = InventoryCountItem::query()->where('session_id', $sessionId)->firstOrFail();

        app(InventoryCountManager::class)->recordCount(
            (string) $item->getKey(),
            '9.000',
            'Comptage physique.',
            (string) $counter->getKey(),
        );

        $details = app(InventoryCountQueryService::class)->show(
            $sessionId,
            (string) $counter->getKey(),
        );
        $itemData = $details['items'][0];

        $this->assertSame('9.000', $itemData['counted_quantity']);
        $this->assertArrayNotHasKey('expected_quantity', $itemData);
        $this->assertArrayNotHasKey('difference_quantity', $itemData);
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
