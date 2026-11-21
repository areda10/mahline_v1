<?php

declare(strict_types=1);

namespace Tests\Feature\Domains\Inventory;

use App\Domains\Cooperatives\Models\Cooperative;
use App\Domains\Identity\Authorization\Models\Role;
use App\Domains\Identity\Users\Models\User;
use App\Domains\Inventory\Contracts\InventoryQuarantineManager;
use App\Domains\Inventory\Contracts\InventoryReservationManager;
use App\Domains\Inventory\DTOs\InventoryReservationLineData;
use App\Domains\Inventory\Enums\InventoryInspectionResult;
use App\Domains\Inventory\Enums\InventoryStockableType;
use App\Domains\Inventory\Models\InventoryQuarantine;
use App\Domains\Inventory\Models\InventoryQuarantineAuthorization;
use App\Domains\Inventory\Models\InventoryReservation;
use App\Domains\Inventory\Models\InventoryStock;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

final class InventoryQuarantineServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([RoleSeeder::class, PermissionSeeder::class, RolePermissionSeeder::class]);
    }

    public function test_quarantine_authorization_is_allocated_and_revocation_protects_existing_reservation(): void
    {
        $cooperative = Cooperative::factory()->active()->create();
        $admin = $this->userWithRole('cooperative_admin', [
            'cooperative_id' => $cooperative->getKey(),
        ]);
        $customer = $this->userWithRole('customer');
        $stockableId = (string) Str::ulid();

        $stock = InventoryStock::query()->create([
            'cooperative_id' => (string) $cooperative->getKey(),
            'stockable_type' => InventoryStockableType::PRODUCT->value,
            'stockable_id' => $stockableId,
            'unit_code' => 'kg',
            'quantity_on_hand' => '10.000',
        ]);

        $quarantineId = app(InventoryQuarantineManager::class)->receiveReturn(
            (string) $cooperative->getKey(),
            InventoryStockableType::PRODUCT,
            $stockableId,
            '2.000',
            'customer_return',
            (string) Str::ulid(),
            'Retour client reçu et isolé.',
            (string) $admin->getKey(),
            'test-quarantine-receive-01',
        );

        app(InventoryQuarantineManager::class)->inspect(
            $quarantineId,
            InventoryInspectionResult::NON_COMPLIANT,
            'Produit non conforme mais éligible à une vente conditionnelle.',
            (string) $admin->getKey(),
            'test-quarantine-inspect-01',
        );

        $authorizationId = app(InventoryQuarantineManager::class)->authorizeSale(
            $quarantineId,
            '1.500',
            'Vente conditionnelle sous responsabilité de la coopérative.',
            (string) $admin->getKey(),
            'test-quarantine-authorize-01',
        );

        $cooperativeOrderId = (string) Str::ulid();
        $orderItemId = (string) Str::ulid();
        app(InventoryReservationManager::class)->reserveForSuborder(
            $cooperativeOrderId,
            (string) $cooperative->getKey(),
            [
                new InventoryReservationLineData(
                    orderItemId: $orderItemId,
                    stockableType: InventoryStockableType::PRODUCT,
                    stockableId: $stockableId,
                    quantity: '11.000',
                    unitCode: 'kg',
                ),
            ],
            'test-quarantine-reservation-01',
            (string) $customer->getKey(),
        );

        $authorization = InventoryQuarantineAuthorization::query()->findOrFail($authorizationId);
        $this->assertSame('1.000', $authorization->quantity_reserved);
        $this->assertSame('1.500', $authorization->quantity_remaining);

        app(InventoryQuarantineManager::class)->revokeSale(
            $authorizationId,
            'Révocation de précaution.',
            (string) $admin->getKey(),
            'test-quarantine-revoke-01',
        );

        $authorization->refresh();
        $quarantine = InventoryQuarantine::query()->findOrFail($quarantineId);
        $stock->refresh();

        $this->assertSame('revoked', $authorization->status);
        $this->assertSame('1.000', $authorization->quantity_reserved);
        $this->assertSame('1.000', $authorization->quantity_remaining);
        $this->assertSame('1.000', $stock->quantity_quarantine_authorized);
        $this->assertSame('1.000', $stock->quantity_reserved_quarantine);
        $this->assertSame('1.000', $quarantine->quantity_authorized);

        app(InventoryReservationManager::class)->releaseForSuborder(
            $cooperativeOrderId,
            'test-quarantine-release-01',
            'Sous-commande annulée avant confirmation.',
            (string) $customer->getKey(),
        );

        $authorization->refresh();
        $quarantine->refresh();
        $stock->refresh();
        $reservation = InventoryReservation::query()
            ->where('cooperative_order_id', $cooperativeOrderId)
            ->firstOrFail();

        $this->assertSame('released', $reservation->status);
        $this->assertSame('0.000', $authorization->quantity_reserved);
        $this->assertSame('0.000', $authorization->quantity_remaining);
        $this->assertSame('0.000', $quarantine->quantity_authorized);
        $this->assertSame('0.000', $stock->quantity_quarantine_authorized);
        $this->assertSame('0.000', $stock->quantity_reserved_quarantine);
        $this->assertSame('12.000', $stock->quantity_on_hand);
        $this->assertSame('2.000', $stock->quantity_quarantined);
    }

    private function userWithRole(string $roleSlug, array $attributes = []): User
    {
        $role = Role::query()
            ->where('slug', $roleSlug)
            ->firstOrFail();

        $user = User::factory()->create(array_merge([
            'status' => 'active',
            'email_verified_at' => now(),
        ], $attributes));
        $user->roles()->attach($role->getKey());

        return $user;
    }
}
