<?php

declare(strict_types=1);

namespace Tests\Feature\Domains\Products;

use App\Domains\Cooperatives\Models\Cooperative;
use App\Domains\Categories\Models\Category;
use App\Domains\Identity\Authentication\Http\Middleware\EnsureActiveAuthenticationSession;
use App\Domains\Identity\Authorization\Models\Permission;
use App\Domains\Identity\Authorization\Models\Role;
use App\Domains\Identity\Enums\UserStatus;
use App\Domains\Identity\Users\Models\User;
use App\Domains\Products\Models\Product;
use App\Domains\Products\Models\ProductUnit;
use App\Domains\Products\Models\ProductVariant;
use App\Domains\Products\Services\ProductVariantService;
use Database\Seeders\ProductUnitSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class ProductVariantManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ProductUnitSeeder::class);
    }


    public function test_cooperative_admin_can_create_a_product_with_variants(): void
    {
        $cooperative = Cooperative::factory()->active()->create();
        $admin = $this->userWithPermissions('cooperative_admin', (string) $cooperative->getKey());
        $category = Category::factory()->forCooperative($cooperative)->create();
        $unit = ProductUnit::query()->where('code', 'kg')->firstOrFail();

        $this->actingAs($admin)
            ->withoutMiddleware(EnsureActiveAuthenticationSession::class)
            ->post(route('backoffice.products.store', [
                'cooperative' => $cooperative->getKey(),
            ]), [
                'product_type' => 'variant',
                'name' => 'Produit multi-formats',
                'description' => 'Produit créé depuis le backoffice coopérative.',
                'variants' => [[
                    'name' => 'Format 1 kg',
                    'price' => '35.00',
                    'unit_id' => $unit->getKey(),
                    'sku' => 'MULTI-1KG',
                ]],
                'category_ids' => [$category->getKey()],
                'is_active' => '1',
            ])
            ->assertRedirect();

        $product = Product::query()
            ->where('cooperative_id', $cooperative->getKey())
            ->where('name', 'Produit multi-formats')
            ->firstOrFail();

        $this->assertSame('variant', $product->product_type);
        $this->assertNull($product->price);
        $this->assertNull($product->unit_id);
        $this->assertDatabaseHas('product_variants', [
            'product_id' => $product->getKey(),
            'label' => 'Format 1 kg',
            'price' => '35.00',
            'unit_id' => $unit->getKey(),
        ]);
    }

    public function test_cooperative_admin_can_create_and_edit_variants_in_own_cooperative(): void
    {
        $cooperative = Cooperative::factory()->active()->create();
        $admin = $this->userWithPermissions('cooperative_admin', (string) $cooperative->getKey());
        $product = $this->variantProduct($cooperative);
        $unit = ProductUnit::query()->where('code', 'kg')->firstOrFail();

        $this->actingAs($admin)
            ->withoutMiddleware(EnsureActiveAuthenticationSession::class)
            ->get(route('backoffice.products.variants.create', [
                'cooperative' => $cooperative->getKey(),
                'product' => $product->getKey(),
            ]))
            ->assertOk()
            ->assertViewIs('backoffice.products.variants.create');

        $this->post(route('backoffice.products.variants.store', [
            'cooperative' => $cooperative->getKey(),
            'product' => $product->getKey(),
        ]), [
            'name' => 'Format 500 g',
            'price' => '18.50',
            'unit_id' => $unit->getKey(),
            'sku' => 'FORMAT-500',
            'is_active' => '1',
        ])->assertRedirect(route('backoffice.products.show', [
            'cooperative' => $cooperative->getKey(),
            'product' => $product->getKey(),
        ]));

        $variant = ProductVariant::query()
            ->where('product_id', $product->getKey())
            ->where('normalized_label', 'format 500 g')
            ->firstOrFail();

        $this->get(route('backoffice.products.variants.edit', [
            'cooperative' => $cooperative->getKey(),
            'product' => $product->getKey(),
            'variant' => $variant->getKey(),
        ]))->assertOk()->assertViewIs('backoffice.products.variants.edit');

        $this->put(route('backoffice.products.variants.update', [
            'cooperative' => $cooperative->getKey(),
            'product' => $product->getKey(),
            'variant' => $variant->getKey(),
        ]), [
            'name' => 'Format 500 g modifié',
            'price' => '19.00',
            'unit_id' => $unit->getKey(),
            'sku' => 'FORMAT-500-B',
            'is_active' => '1',
        ])->assertRedirect(route('backoffice.products.show', [
            'cooperative' => $cooperative->getKey(),
            'product' => $product->getKey(),
        ]));

        $this->assertDatabaseHas('product_variants', [
            'id' => $variant->getKey(),
            'label' => 'Format 500 g modifié',
            'price' => '19.00',
            'sku' => 'FORMAT-500-B',
            'is_active' => true,
        ]);
    }

    public function test_super_admin_cannot_edit_variant_of_unassigned_cooperative(): void
    {
        $cooperative = Cooperative::factory()->active()->create();
        $admin = $this->userWithPermissions('super_admin');
        $product = $this->variantProduct($cooperative);
        $variant = app(ProductVariantService::class)->create($product, [
            'name' => 'Format 250 g',
            'price' => '12.00',
            'unit_id' => (string) ProductUnit::query()->where('code', 'kg')->value('id'),
        ]);

        $this->actingAs($admin)
            ->withoutMiddleware(EnsureActiveAuthenticationSession::class)
            ->get(route('backoffice.products.variants.edit', [
                'cooperative' => $cooperative->getKey(),
                'product' => $product->getKey(),
                'variant' => $variant->getKey(),
            ]))
            ->assertForbidden();
    }

    public function test_assigned_super_admin_can_edit_variant_of_assigned_cooperative(): void
    {
        $cooperative = Cooperative::factory()->active()->create();
        $admin = $this->userWithPermissions('super_admin');
        $admin->cooperatives()->attach($cooperative->getKey());
        $product = $this->variantProduct($cooperative);
        $unit = ProductUnit::query()->where('code', 'kg')->firstOrFail();
        $variant = app(ProductVariantService::class)->create($product, [
            'name' => 'Format 250 g',
            'price' => '12.00',
            'unit_id' => (string) $unit->getKey(),
        ]);

        $this->actingAs($admin)
            ->withoutMiddleware(EnsureActiveAuthenticationSession::class)
            ->put(route('backoffice.products.variants.update', [
                'cooperative' => $cooperative->getKey(),
                'product' => $product->getKey(),
                'variant' => $variant->getKey(),
            ]), [
                'name' => 'Format 250 g assigné',
                'price' => '13.00',
                'unit_id' => $unit->getKey(),
                'is_active' => '1',
            ])
            ->assertRedirect(route('backoffice.products.show', [
                'cooperative' => $cooperative->getKey(),
                'product' => $product->getKey(),
            ]));

        $this->assertSame('Format 250 g assigné', $variant->fresh()->label);
    }


    public function test_variant_parent_remains_editable_when_all_variants_are_soft_deleted(): void
    {
        $cooperative = Cooperative::factory()->active()->create();
        $admin = $this->userWithPermissions('cooperative_admin', (string) $cooperative->getKey());
        $product = $this->variantProduct($cooperative);
        $variant = app(ProductVariantService::class)->create($product, [
            'name' => 'Variante supprimée',
            'price' => '10.00',
            'unit_id' => (string) ProductUnit::query()->where('code', 'kg')->value('id'),
        ]);
        app(ProductVariantService::class)->delete($variant);

        $this->actingAs($admin)
            ->withoutMiddleware(EnsureActiveAuthenticationSession::class)
            ->put(route('backoffice.products.update', [
                'cooperative' => $cooperative->getKey(),
                'product' => $product->getKey(),
            ]), [
                'name' => 'Produit parent modifié',
            ])
            ->assertRedirect(route('backoffice.products.show', [
                'cooperative' => $cooperative->getKey(),
                'product' => $product->getKey(),
            ]));

        $this->assertSame('Produit parent modifié', $product->fresh()->name);
        $this->assertSame('variant', $product->fresh()->product_type);
    }

    private function variantProduct(Cooperative $cooperative): Product
    {
        return Product::factory()->forCooperative($cooperative)->create([
            'product_type' => 'variant',
            'price' => null,
            'unit_id' => null,
            'is_active' => true,
        ]);
    }

    private function userWithPermissions(string $roleSlug, ?string $cooperativeId = null): User
    {
        $role = Role::query()->firstOrCreate(
            ['slug' => $roleSlug],
            ['name' => $roleSlug, 'is_active' => true],
        );

        $permissionIds = [];
        foreach (['cooperatives.view', 'products.create', 'products.update', 'products.delete', 'products.restore'] as $slug) {
            $permission = Permission::query()->firstOrCreate(
                ['slug' => $slug],
                ['name' => $slug, 'is_active' => true],
            );
            $permissionIds[] = $permission->getKey();
        }
        $role->permissions()->syncWithoutDetaching($permissionIds);

        $user = User::factory()->create([
            'status' => UserStatus::Active,
            'email_verified_at' => now(),
            'cooperative_id' => $cooperativeId,
        ]);
        $user->roles()->attach($role->getKey());

        return $user;
    }
}
