<?php

declare(strict_types=1);

namespace Tests\Framework\Domains\Catalog\Services;

use App\Domains\Catalog\Models\Catalog;
use App\Domains\Catalog\Repositories\CatalogRepository;
use App\Domains\Catalog\Services\CatalogService;
use App\Domains\Cooperatives\Models\Cooperative;
use App\Domains\Products\Models\Product;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

final class CatalogServiceTest extends TestCase
{
    use RefreshDatabase;

    private CatalogService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new CatalogService(
            new CatalogRepository()
        );
    }

    public function test_it_creates_catalog_for_a_cooperative(): void
    {
        $cooperative = Cooperative::factory()->create();

        $catalog = $this->service->createForCooperative(
            (string) $cooperative->getKey(),
            ['name' => 'Catalogue Bio'],
        );

        $this->assertSame('Catalogue Bio', $catalog->name);
        $this->assertSame('catalogue-bio', $catalog->slug);
        $this->assertSame(
            (string) $cooperative->getKey(),
            (string) $catalog->cooperative_id,
        );
    }

    public function test_it_generates_a_unique_slug_within_the_cooperative(): void
    {
        $cooperative = Cooperative::factory()->create();

        $first = $this->service->createForCooperative(
            (string) $cooperative->getKey(),
            ['name' => 'Produits Bio'],
        );

        $second = $this->service->createForCooperative(
            (string) $cooperative->getKey(),
            ['name' => 'Produits Bio'],
        );

        $this->assertSame('produits-bio', $first->slug);
        $this->assertSame('produits-bio-2', $second->slug);
    }

    public function test_it_rejects_an_empty_catalog_name(): void
    {
        $cooperative = Cooperative::factory()->create();

        $this->expectException(ValidationException::class);

        $this->service->createForCooperative(
            (string) $cooperative->getKey(),
            ['name' => '   '],
        );
    }

    public function test_it_updates_catalog_name_and_slug(): void
    {
        $cooperative = Cooperative::factory()->create();

        $catalog = Catalog::factory()->create([
            'cooperative_id' => $cooperative->getKey(),
            'name' => 'Ancien nom',
            'slug' => 'ancien-nom',
        ]);

        $updated = $this->service->update(
            $catalog,
            (string) $cooperative->getKey(),
            ['name' => 'Nouveau catalogue'],
        );

        $this->assertSame('Nouveau catalogue', $updated->name);
        $this->assertSame('nouveau-catalogue', $updated->slug);
    }

    public function test_it_rejects_updates_from_another_cooperative(): void
    {
        $owner = Cooperative::factory()->create();
        $other = Cooperative::factory()->create();

        $catalog = Catalog::factory()->create([
            'cooperative_id' => $owner->getKey(),
        ]);

        $this->expectException(ModelNotFoundException::class);

        $this->service->update(
            $catalog,
            (string) $other->getKey(),
            ['name' => 'Tentative interdite'],
        );
    }

    public function test_it_attaches_a_product_from_the_same_cooperative(): void
    {
        $cooperative = Cooperative::factory()->create();

        $catalog = Catalog::factory()->create([
            'cooperative_id' => $cooperative->getKey(),
            'is_active' => true,
        ]);

        $product = Product::factory()->create([
            'cooperative_id' => $cooperative->getKey(),
            'is_active' => true,
        ]);

        $this->service->addProduct(
            $catalog,
            (string) $cooperative->getKey(),
            (string) $product->getKey(),
        );

        $this->assertDatabaseHas('catalog_product', [
            'catalog_id' => $catalog->getKey(),
            'product_id' => $product->getKey(),
        ]);
    }

    public function test_it_rejects_a_product_from_another_cooperative(): void
    {
        $owner = Cooperative::factory()->create();
        $other = Cooperative::factory()->create();

        $catalog = Catalog::factory()->create([
            'cooperative_id' => $owner->getKey(),
            'is_active' => true,
        ]);

        $product = Product::factory()->create([
            'cooperative_id' => $other->getKey(),
            'is_active' => true,
        ]);

        try {
            $this->service->addProduct(
                $catalog,
                (string) $owner->getKey(),
                (string) $product->getKey(),
            );

            $this->fail('Le service aurait dû refuser ce produit.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey(
                'product_id',
                $exception->errors(),
            );
        }

        $this->assertDatabaseMissing('catalog_product', [
            'catalog_id' => $catalog->getKey(),
            'product_id' => $product->getKey(),
        ]);
    }

    public function test_it_removes_a_product_without_deleting_the_product(): void
    {
        $cooperative = Cooperative::factory()->create();

        $catalog = Catalog::factory()->create([
            'cooperative_id' => $cooperative->getKey(),
        ]);

        $product = Product::factory()->create([
            'cooperative_id' => $cooperative->getKey(),
        ]);

        $catalog->products()->attach($product->getKey());

        $this->service->removeProduct(
            $catalog,
            (string) $cooperative->getKey(),
            (string) $product->getKey(),
        );

        $this->assertDatabaseMissing('catalog_product', [
            'catalog_id' => $catalog->getKey(),
            'product_id' => $product->getKey(),
        ]);

        $this->assertDatabaseHas('products', [
            'id' => $product->getKey(),
        ]);
    }

    public function test_it_soft_deletes_catalog_without_deleting_products(): void
    {
        $cooperative = Cooperative::factory()->create();

        $catalog = Catalog::factory()->create([
            'cooperative_id' => $cooperative->getKey(),
        ]);

        $product = Product::factory()->create([
            'cooperative_id' => $cooperative->getKey(),
        ]);

        $catalog->products()->attach($product->getKey());

        $this->service->delete(
            $catalog,
            (string) $cooperative->getKey(),
        );

        $this->assertSoftDeleted('catalogs', [
            'id' => $catalog->getKey(),
        ]);

        $this->assertDatabaseHas('products', [
            'id' => $product->getKey(),
        ]);
    }
}
