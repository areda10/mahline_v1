<?php

declare(strict_types=1);

namespace Tests\Framework\Domains\Products\Repositories;

use App\Domains\Cooperatives\Models\Cooperative;
use App\Domains\Products\Models\Product;
use App\Domains\Products\Repositories\ProductRepository;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class ProductRepositoryTest extends TestCase
{
    use RefreshDatabase;

    private ProductRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repository = app(ProductRepository::class);
    }

    public function test_it_paginates_products_for_one_cooperative(): void
    {
        $cooperativeA = Cooperative::factory()->create();
        $cooperativeB = Cooperative::factory()->create();

        Product::factory()->count(3)->create([
            'cooperative_id' => $cooperativeA->getKey(),
        ]);

        Product::factory()->count(2)->create([
            'cooperative_id' => $cooperativeB->getKey(),
        ]);

        $page = $this->repository->paginateForCooperative(
            (string) $cooperativeA->getKey(),
        );

        $this->assertSame(3, $page->total());

        foreach ($page->items() as $product) {
            $this->assertSame(
                $cooperativeA->getKey(),
                $product->cooperative_id,
            );
        }
    }

    public function test_it_finds_a_product_owned_by_the_cooperative(): void
    {
        $cooperative = Cooperative::factory()->create();

        $product = Product::factory()->create([
            'cooperative_id' => $cooperative->getKey(),
        ]);

        $result = $this->repository->findForCooperativeOrFail(
            (string) $product->getKey(),
            (string) $cooperative->getKey(),
        );

        $this->assertSame($product->getKey(), $result->getKey());
    }

    public function test_it_does_not_find_another_cooperatives_product(): void
    {
        $cooperativeA = Cooperative::factory()->create();
        $cooperativeB = Cooperative::factory()->create();

        $product = Product::factory()->create([
            'cooperative_id' => $cooperativeA->getKey(),
        ]);

        $this->expectException(ModelNotFoundException::class);

        $this->repository->findForCooperativeOrFail(
            (string) $product->getKey(),
            (string) $cooperativeB->getKey(),
        );
    }

    public function test_it_creates_a_product(): void
    {
        $cooperative = Cooperative::factory()->create();

        $product = $this->repository->create([
            'cooperative_id' => $cooperative->getKey(),
            'name' => 'Miel de montagne',
            'slug' => 'miel-de-montagne-test',
            'description' => 'Produit de démonstration.',
            'is_active' => true,
        ]);

        $this->assertDatabaseHas('products', [
            'id' => $product->getKey(),
            'cooperative_id' => $cooperative->getKey(),
            'name' => 'Miel de montagne',
        ]);
    }

    public function test_it_updates_a_product(): void
    {
        $product = Product::factory()->create([
            'name' => 'Ancien nom',
        ]);

        $updated = $this->repository->update($product, [
            'name' => 'Nouveau nom',
        ]);

        $this->assertSame('Nouveau nom', $updated->name);

        $this->assertDatabaseHas('products', [
            'id' => $product->getKey(),
            'name' => 'Nouveau nom',
        ]);
    }

    public function test_delete_performs_a_soft_delete(): void
    {
        $product = Product::factory()->create();

        $productId = $product->getKey();

        $this->repository->delete($product);

        $this->assertSoftDeleted('products', [
            'id' => $productId,
        ]);
    }
}
