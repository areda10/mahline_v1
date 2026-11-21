<?php

declare(strict_types=1);

namespace Tests\Feature\Domains\Inventory;

use App\Domains\Cooperatives\Models\Cooperative;
use App\Domains\Identity\Authentication\Http\Middleware\EnsureActiveAuthenticationSession;
use App\Domains\Inventory\Enums\InventoryStockableType;
use App\Domains\Inventory\Models\InventoryStock;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Support\Concerns\CreatesAuthorizedCooperativeAdmin;
use Tests\TestCase;

final class InventoryWorkspaceTest extends TestCase
{
    use CreatesAuthorizedCooperativeAdmin;
    use RefreshDatabase;

    public function test_inventory_workspace_shows_status_without_exposing_stock_quantities(): void
    {
        $cooperative = Cooperative::factory()->active()->create();
        $admin = $this->createCooperativeAdminFor($cooperative);
        $this->actingAs($admin);
        $this->withoutMiddleware(EnsureActiveAuthenticationSession::class);

        InventoryStock::query()->create([
            'cooperative_id' => (string) $cooperative->getKey(),
            'stockable_type' => InventoryStockableType::PRODUCT->value,
            'stockable_id' => (string) Str::ulid(),
            'unit_code' => 'kg',
            'quantity_on_hand' => '5.000',
        ]);

        $response = $this->get(route('backoffice.inventory.index'));

        $response->assertOk()
            ->assertViewIs('backoffice.inventory.index')
            ->assertSee('En stock')
            ->assertDontSee('5.000');
    }
}
