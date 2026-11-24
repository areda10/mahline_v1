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

        // Simulate the same-origin Fetch Metadata header sent by browsers.
        $this->withHeader('Sec-Fetch-Site', 'same-origin');

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
            ->assertSee('Unité / pièce (u)')
            ->assertDontSee('Millilitre (ml)')
            ->assertSee('name="unit_id"', false);
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

    public function test_root_can_create_a_variant_product_with_independent_units_and_prices(): void
    {
        $root = $this->actor(['cooperatives.view', 'products.create']);
        $cooperative = Cooperative::factory()->active()->create();
        $category = Category::factory()->forCooperative($cooperative)->create();
        $unitId = (string) ProductUnit::query()->where('code', 'u')->value('id');

        $this->actingAs($root)
            ->post(route('root.backoffice.cooperatives.products.store', [
                'cooperative' => $cooperative->getKey(),
            ]), [
                'product_type' => 'variant',
                'name' => 'Miel naturel',
                'description' => 'Miel de la coopérative',
                'category_ids' => [$category->getKey()],
                'variants' => [
                    ['name' => 'Pot 250 g', 'price' => '45.00', 'unit_id' => $unitId, 'sku' => 'MIEL-250'],
                    ['name' => 'Pot 500 g', 'price' => '80.00', 'unit_id' => $unitId, 'sku' => 'MIEL-500'],
                ],
                'is_active' => '1',
            ])
            ->assertRedirect();

        $product = Product::query()->where('cooperative_id', $cooperative->getKey())
            ->where('name', 'Miel naturel')->firstOrFail();

        $this->assertSame('variant', $product->product_type);
        $this->assertNull($product->unit_id);
        $this->assertNull($product->price);
        $this->assertDatabaseHas('product_variants', [
            'product_id' => $product->getKey(),
            'label' => 'Pot 250 g',
            'price' => '45.00',
            'unit_id' => $unitId,
            'sku' => 'MIEL-250',
        ]);
        $this->assertDatabaseHas('product_variants', [
            'product_id' => $product->getKey(),
            'label' => 'Pot 500 g',
            'price' => '80.00',
            'unit_id' => $unitId,
            'sku' => 'MIEL-500',
        ]);
        $this->assertSame(2, ProductVariant::query()->where('product_id', $product->getKey())->count());
    }

    public function test_root_variant_product_requires_at_least_one_variant(): void
    {
        $root = $this->actor(['cooperatives.view', 'products.create']);
        $cooperative = Cooperative::factory()->active()->create();
        $category = Category::factory()->forCooperative($cooperative)->create();

        $this->actingAs($root)
            ->from(route('root.backoffice.cooperatives.products.create', ['cooperative' => $cooperative->getKey()]))
            ->post(route('root.backoffice.cooperatives.products.store', ['cooperative' => $cooperative->getKey()]), [
                'product_type' => 'variant',
                'name' => 'Miel sans format',
                'category_ids' => [$category->getKey()],
            ])
            ->assertRedirect()
            ->assertSessionHasErrors(['variants']);
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
