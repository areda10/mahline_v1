<?php

declare(strict_types=1);

namespace Tests\Feature\Domains\Products;

use App\Domains\Catalog\Models\Catalog;
use App\Domains\Categories\Models\Category;
use App\Domains\Cooperatives\Models\Cooperative;
use App\Domains\Identity\Authorization\Models\Role;
use App\Domains\Identity\Enums\UserStatus;
use App\Domains\Identity\Users\Models\User;
use App\Domains\Products\Models\Product;
use App\Domains\Products\Models\ProductUnit;
use App\Domains\Products\Models\ProductVariant;
use Database\Seeders\ProductUnitSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

final class ProductStorefrontTest extends TestCase
{
    use RefreshDatabase;

    private string $unitId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withHeader('Sec-Fetch-Site', 'same-origin');
        $this->seed(ProductUnitSeeder::class);
        $this->unitId = (string) ProductUnit::query()->where('code', 'kg')->value('id');
    }

    public function test_public_shop_lists_only_active_products_from_active_cooperatives_and_active_catalogs(): void
    {
        [$visible] = $this->publishedProduct('Miel de montagne');
        [$inactiveProduct] = $this->publishedProduct('Savon désactivé', ['is_active' => false]);
        [$inactiveCooperativeProduct] = $this->publishedProduct('Huile coopérative fermée', [], false);
        [$inactiveCatalogProduct, $inactiveCatalog] = $this->publishedProduct('Produit catalogue fermé');
        $inactiveCatalog->forceFill(['is_active' => false])->save();

        $response = $this->get(route('storefront.products.index'));

        $response->assertOk()
            ->assertViewIs('storefront.products.index')
            ->assertSee('Miel de montagne')
            ->assertDontSee('Savon désactivé')
            ->assertDontSee('Huile coopérative fermée')
            ->assertDontSee('Produit catalogue fermé');

        $this->assertNotNull($visible->getKey());
        $this->assertNotNull($inactiveProduct->getKey());
        $this->assertNotNull($inactiveCooperativeProduct->getKey());
        $this->assertNotNull($inactiveCatalogProduct->getKey());
    }

    public function test_public_shop_search_and_category_filter_return_matching_products(): void
    {
        [$first, $catalog] = $this->publishedProduct('Miel de montagne');
        [$second] = $this->publishedProduct('Savon naturel');
        $category = Category::factory()->global()->create(['name' => 'Produits de la ruche']);
        $first->categories()->attach($category);

        $search = $this->get(route('storefront.products.index', ['q' => 'Miel']));
        $search->assertOk()->assertSee('Miel de montagne')->assertDontSee('Savon naturel');

        $filtered = $this->get(route('storefront.products.index', [
            'category_id' => (string) $category->getKey(),
        ]));
        $filtered->assertOk()->assertSee('Miel de montagne')->assertDontSee('Savon naturel');

        $this->assertTrue($catalog->products()->whereKey($first->getKey())->exists());
        $this->assertNotSame((string) $first->getKey(), (string) $second->getKey());
    }

    public function test_product_detail_shows_only_active_variants_and_requires_variant_selection(): void
    {
        [$product] = $this->publishedProduct('Huile d’olive', ['unit_id' => null]);
        $active = ProductVariant::factory()->for($product)->create([
            'label' => '500 ml',
            'normalized_label' => '500 ml',
            'price' => '45.00',
            'unit_id' => $this->unitId,
            'is_active' => true,
        ]);
        ProductVariant::factory()->for($product)->create([
            'label' => '1 litre désactivé',
            'normalized_label' => '1 litre desactive',
            'price' => '80.00',
            'unit_id' => $this->unitId,
            'is_active' => false,
        ]);

        $this->get(route('storefront.products.show', $product))
            ->assertOk()
            ->assertViewIs('storefront.products.show')
            ->assertSee('Choisir une variante')
            ->assertSee('500 ml')
            ->assertDontSee('1 litre désactivé');

        $this->assertTrue($active->is_active);
    }

    public function test_product_detail_does_not_publish_product_without_an_active_catalog(): void
    {
        [$product, $catalog] = $this->publishedProduct('Produit non publié');
        $catalog->forceFill(['is_active' => false])->save();

        $this->get(route('storefront.products.show', $product))->assertNotFound();
    }

    public function test_authenticated_customer_can_navigate_from_shop_to_account_orders_and_profile(): void
    {
        $role = Role::query()->firstOrCreate(
            ['slug' => 'customer'],
            ['name' => 'Customer', 'is_active' => true],
        );
        $customer = User::factory()->create([
            'status' => UserStatus::Active,
            'email_verified_at' => now(),
        ]);
        $customer->roles()->attach($role->getKey());

        $this->actingAs($customer)
            ->get(route('storefront.products.index'))
            ->assertOk()
            ->assertSee(route('customer.dashboard'))
            ->assertSee(route('customer.orders.index'))
            ->assertSee(route('customer.profile.edit'));

        $this->actingAs($customer)
            ->get(route('customer.dashboard'))
            ->assertOk()
            ->assertSee('Parcourir la boutique')
            ->assertSee('Voir mon panier')
            ->assertSee('Suivre mes commandes')
            ->assertSee('Profil et carnet d’adresses')
            ->assertSee('Mes notifications');
    }

    private function publishedProduct(string $name, array $productAttributes = [], bool $activeCooperative = true): array
    {
        $cooperative = $activeCooperative
            ? Cooperative::factory()->active()->create()
            : Cooperative::factory()->inactive()->create();

        $catalog = Catalog::factory()->create([
            'cooperative_id' => $cooperative->getKey(),
            'is_active' => true,
        ]);

        $product = Product::factory()->forCooperative($cooperative)->create(array_merge([
            'name' => $name,
            'slug' => Str::slug($name).'-'.Str::lower(Str::random(5)),
            'unit_id' => $this->unitId,
            'is_active' => true,
        ], $productAttributes));

        $catalog->products()->attach($product);

        return [$product, $catalog, $cooperative];
    }
}
