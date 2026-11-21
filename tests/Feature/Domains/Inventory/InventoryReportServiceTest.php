<?php

declare(strict_types=1);

namespace Tests\Feature\Domains\Inventory;

use App\Domains\Cooperatives\Models\Cooperative;
use App\Domains\Identity\Authorization\Models\Role;
use App\Domains\Identity\Enums\UserStatus;
use App\Domains\Identity\Users\Models\User;
use App\Domains\Inventory\Enums\InventoryStockableType;
use App\Domains\Inventory\Models\InventoryStock;
use App\Domains\Inventory\Services\Reports\InventoryReportService;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

final class InventoryReportServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([RoleSeeder::class, PermissionSeeder::class, RolePermissionSeeder::class]);
    }

    public function test_user_can_export_only_their_simplified_cooperative_pdf_report(): void
    {
        $cooperative = Cooperative::factory()->active()->create();
        $user = $this->userWithRole('user', [
            'cooperative_id' => $cooperative->getKey(),
        ]);

        InventoryStock::query()->create([
            'cooperative_id' => (string) $cooperative->getKey(),
            'stockable_type' => InventoryStockableType::PRODUCT->value,
            'stockable_id' => (string) Str::ulid(),
            'unit_code' => 'u',
            'quantity_on_hand' => '8.000',
        ]);

        $pdf = app(InventoryReportService::class)->pdf(
            (string) $cooperative->getKey(),
            (string) $user->getKey(),
        );

        $this->assertStringStartsWith('%PDF-', $pdf);
    }

    public function test_user_cannot_export_another_cooperatives_inventory_report(): void
    {
        $ownCooperative = Cooperative::factory()->active()->create();
        $otherCooperative = Cooperative::factory()->active()->create();
        $user = $this->userWithRole('user', [
            'cooperative_id' => $ownCooperative->getKey(),
        ]);

        $this->expectException(AuthorizationException::class);

        app(InventoryReportService::class)->pdf(
            (string) $otherCooperative->getKey(),
            (string) $user->getKey(),
        );
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
