<?php

declare(strict_types=1);

namespace Tests\Feature\Domains\Identity\SuperAdminRoot\Http\Controllers;

use App\Domains\Cooperatives\Models\Cooperative;
use App\Domains\Identity\Authentication\Http\Middleware\EnsureActiveAuthenticationSession;
use App\Domains\Identity\Authorization\Models\Permission;
use App\Domains\Identity\Authorization\Models\Role;
use App\Domains\Identity\Users\Models\User;
use App\Domains\Products\Models\ProductUnit;
use Database\Seeders\ProductUnitSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class ProductControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(EnsureActiveAuthenticationSession::class);
        $this->seed(ProductUnitSeeder::class);
    }

    public function test_root_product_form_displays_only_active_registered_units(): void
    {
        $root = $this->actor(['cooperatives.view', 'products.create']);
        $cooperative = Cooperative::factory()->active()->create();

        $inactive = ProductUnit::query()->where('code', 'ml')->firstOrFail();
        $inactive->update(['is_active' => false]);

        $this->actingAs($root)
            ->get(route('root.backoffice.cooperatives.products.create', [
                'cooperative' => $cooperative->getKey(),
            ]))
            ->assertOk()
            ->assertViewIs('super-admin-root.cooperatives.products.create')
            ->assertSee('Kilogramme (kg)')
            ->assertSee('Gramme (g)')
            ->assertSee('Litre (L)')
            ->assertDontSee('Millilitre (ml)')
            ->assertSee('name="unit_id"');
    }

    public function test_root_product_creation_rejects_a_missing_unit(): void
    {
        $root = $this->actor(['cooperatives.view', 'products.create']);
        $cooperative = Cooperative::factory()->active()->create();

        $this->actingAs($root)
            ->from(route('root.backoffice.cooperatives.products.create', [
                'cooperative' => $cooperative->getKey(),
            ]))
            ->post(route('root.backoffice.cooperatives.products.store', [
                'cooperative' => $cooperative->getKey(),
            ]), [
                'name' => 'Produit sans unité',
                'price' => '25.00',
            ])
            ->assertRedirect()
            ->assertSessionHasErrors(['unit_id']);
    }

    private function actor(array $permissions): User
    {
        $user = User::factory()->create();
        $role = Role::query()->firstOrCreate(
            ['slug' => 'super_admin_root'],
            ['name' => 'Super Admin Root', 'is_active' => true],
        );

        foreach ($permissions as $slug) {
            $permission = Permission::query()->firstOrCreate(
                ['slug' => $slug],
                ['name' => $slug, 'is_active' => true],
            );
            $role->permissions()->syncWithoutDetaching([$permission->getKey()]);
        }

        $user->roles()->syncWithoutDetaching([$role->getKey()]);

        return $user;
    }
}
