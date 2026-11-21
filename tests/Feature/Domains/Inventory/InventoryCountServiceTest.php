<?php

declare(strict_types=1);

namespace Tests\Feature\Domains\Inventory;

use App\Domains\Cooperatives\Models\Cooperative;
use App\Domains\Identity\Authentication\Http\Middleware\EnsureActiveAuthenticationSession;
use App\Domains\Identity\Authorization\Models\Role;
use App\Domains\Identity\Enums\UserStatus;
use App\Domains\Identity\Users\Models\User;
use App\Domains\Inventory\Contracts\InventoryCountManager;
use App\Domains\Inventory\Enums\InventoryStockableType;
use App\Domains\Inventory\Models\InventoryCountItem;
use App\Domains\Inventory\Models\InventoryCountItemEvent;
use App\Domains\Inventory\Models\InventoryCountSession;
use App\Domains\Inventory\Models\InventoryStock;
use App\Domains\Inventory\Services\Counts\InventoryCountQueryService;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

final class InventoryCountServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([RoleSeeder::class, PermissionSeeder::class, RolePermissionSeeder::class]);
    }

    public function test_counting_can_be_partial_and_corrections_are_historized_until_validation(): void
    {
        $cooperative = Cooperative::factory()->active()->create();
        $admin = $this->userWithRole('cooperative_admin', [
            'cooperative_id' => $cooperative->getKey(),
        ]);
        $counter = $this->userWithRole('user', [
            'cooperative_id' => $cooperative->getKey(),
        ]);

        $first = $this->stock($cooperative, '10.000');
        $second = $this->stock($cooperative, '20.000');

        $manager = app(InventoryCountManager::class);
        $sessionId = $manager->createSession(
            (string) $cooperative->getKey(),
            [(string) $first->getKey(), (string) $second->getKey()],
            (string) $admin->getKey(),
            'count-session-create-001',
        );

        $items = InventoryCountItem::query()->where('session_id', $sessionId)->get()->keyBy('stock_id');
        $firstItem = $items->get((string) $first->getKey());
        $secondItem = $items->get((string) $second->getKey());

        $manager->recordCount(
            (string) $firstItem->getKey(),
            '9.500',
            'Écart constaté lors du premier comptage.',
            (string) $counter->getKey(),
        );
        $manager->recordCount(
            (string) $firstItem->getKey(),
            '9.400',
            'Correction après recomptage contradictoire.',
            (string) $counter->getKey(),
        );

        $this->assertSame(2, InventoryCountItemEvent::query()
            ->where('count_item_id', $firstItem->getKey())
            ->count());

        $manager->validateSession($sessionId, (string) $admin->getKey());

        $session = InventoryCountSession::query()->findOrFail($sessionId);
        $first->refresh();
        $second->refresh();
        $secondItem->refresh();

        $this->assertSame('partially_validated', $session->status);
        $this->assertSame('9.400', $first->quantity_on_hand);
        $this->assertNull($first->active_count_session_id);
        $this->assertSame($sessionId, $second->active_count_session_id);
        $this->assertSame('pending_count', $secondItem->status);

        $manager->recordCount(
            (string) $secondItem->getKey(),
            '21.000',
            'Surplus constaté au second comptage.',
            (string) $counter->getKey(),
        );
        $manager->validateSession($sessionId, (string) $admin->getKey());

        $session->refresh();
        $second->refresh();

        $this->assertSame('completed', $session->status);
        $this->assertSame('21.000', $second->quantity_on_hand);
        $this->assertNull($second->active_count_session_id);
    }

    public function test_cancelled_count_session_preserves_records_and_releases_all_locks(): void
    {
        $cooperative = Cooperative::factory()->active()->create();
        $admin = $this->userWithRole('cooperative_admin', [
            'cooperative_id' => $cooperative->getKey(),
        ]);
        $stock = $this->stock($cooperative, '5.000');

        $manager = app(InventoryCountManager::class);
        $sessionId = $manager->createSession(
            (string) $cooperative->getKey(),
            [(string) $stock->getKey()],
            (string) $admin->getKey(),
            'count-session-create-002',
        );
        $item = InventoryCountItem::query()->where('session_id', $sessionId)->firstOrFail();

        $manager->recordCount(
            (string) $item->getKey(),
            '4.000',
            'Comptage provisoire.',
            (string) $admin->getKey(),
        );
        $manager->cancelSession(
            $sessionId,
            'Session abandonnée après interruption.',
            (string) $admin->getKey(),
        );

        $session = InventoryCountSession::query()->findOrFail($sessionId);
        $item->refresh();
        $stock->refresh();

        $this->assertSame('cancelled', $session->status);
        $this->assertSame('counted', $item->status);
        $this->assertSame('4.000', $item->counted_quantity);
        $this->assertNull($stock->active_count_session_id);
        $this->assertSame('5.000', $stock->quantity_on_hand);
    }

    public function test_count_session_listing_is_scoped_and_does_not_expose_counted_quantities(): void
    {
        $cooperative = Cooperative::factory()->active()->create();
        $admin = $this->userWithRole('cooperative_admin', [
            'cooperative_id' => $cooperative->getKey(),
        ]);
        $counter = $this->userWithRole('user', [
            'cooperative_id' => $cooperative->getKey(),
        ]);
        $stock = $this->stock($cooperative, '8.000');

        $sessionId = app(InventoryCountManager::class)->createSession(
            (string) $cooperative->getKey(),
            [(string) $stock->getKey()],
            (string) $admin->getKey(),
            'count-session-list-001',
        );

        $sessions = app(InventoryCountQueryService::class)->sessionsForCooperative(
            (string) $cooperative->getKey(),
            (string) $counter->getKey(),
        );

        $this->assertCount(1, $sessions);
        $this->assertSame($sessionId, $sessions[0]['id']);
        $this->assertSame(1, $sessions[0]['items_count']);
        $this->assertArrayNotHasKey('expected_quantity', $sessions[0]);
        $this->assertArrayNotHasKey('counted_quantity', $sessions[0]);
    }

    public function test_count_workspace_redacts_theoretical_quantity_for_counter_users(): void
    {
        $cooperative = Cooperative::factory()->active()->create();
        $admin = $this->userWithRole('cooperative_admin', [
            'cooperative_id' => $cooperative->getKey(),
        ]);
        $counter = $this->userWithRole('user', [
            'cooperative_id' => $cooperative->getKey(),
        ]);
        $stock = $this->stock($cooperative, '10.000');
        $sessionId = app(InventoryCountManager::class)->createSession(
            (string) $cooperative->getKey(),
            [(string) $stock->getKey()],
            (string) $admin->getKey(),
            'count-session-workspace-001',
        );

        $this->actingAs($counter);
        $this->withoutMiddleware(EnsureActiveAuthenticationSession::class);

        $this->get(route('backoffice.inventory.count-sessions.workspace', ['sessionId' => $sessionId]))
            ->assertOk()
            ->assertViewIs('backoffice.inventory.count-sessions.show')
            ->assertSee('Enregistrer')
            ->assertDontSee('Théorique');

        $this->actingAs($admin);

        $this->get(route('backoffice.inventory.count-sessions.workspace', ['sessionId' => $sessionId]))
            ->assertOk()
            ->assertSee('Théorique');
    }

    private function stock(Cooperative $cooperative, string $quantity): InventoryStock
    {
        return InventoryStock::query()->create([
            'cooperative_id' => (string) $cooperative->getKey(),
            'stockable_type' => InventoryStockableType::PRODUCT->value,
            'stockable_id' => (string) Str::ulid(),
            'unit_code' => 'kg',
            'quantity_on_hand' => $quantity,
        ]);
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
