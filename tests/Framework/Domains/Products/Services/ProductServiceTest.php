<?php

declare(strict_types=1);

namespace Tests\Framework\Domains\Products\Services;

use App\Domains\Cooperatives\Models\Cooperative;
use App\Domains\Products\Models\Product;
use App\Domains\Products\Services\ProductService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class ProductServiceTest extends TestCase
{
    use RefreshDatabase;

    private ProductService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(ProductService::class);
    }

    public function test_it_creates_a_product_for_the_given_cooperative(): void
    {
        $cooperative = Cooperative::factory()->create();

        $product = $this->service->create(
            (string) $cooperative->getKey(),
            [
                'name' => 'Miel naturel',
                'slug' => 'miel-naturel',
                'description' => 'Miel produit au Maroc.',
                'is_active' => true,
            ],
        );

        $this->assertSame(
            $cooperative->getKey(),
            $product->cooperative_id,
        );

        $this->assertDatabaseHas('products', [
            'id' => $product->getKey(),
            'cooperative_id' => $cooperative->getKey(),
            'name' => 'Miel naturel',
            'is_active' => true,
        ]);
    }

    public function test_it_does_not_return_another_cooperatives_product(): void
    {
        $firstCooperative = Cooperative::factory()->create();
        $secondCooperative = Cooperative::factory()->create();

        $product = $this->service->create(
            (string) $firstCooperative->getKey(),
            [
                'name' => 'Miel naturel',
                'slug' => 'miel-naturel',
                'is_active' => true,
            ],
        );

        $this->assertSame(
            $firstCooperative->getKey(),
            $product->cooperative_id,
        );

        // Un filtre de repository doit exclure ce produit
        // lorsqu'on recherche les produits de la seconde coopérative.
        $repository = app(
            \App\Domains\Products\Repositories\ProductRepository::class,
        );

        $this->assertSame(
            0,
            $repository
                ->paginateForCooperative(
                    (string) $secondCooperative->getKey(),
                )
                ->total(),
        );
    }

    public function test_it_deactivates_a_product_without_deleting_it(): void
    {
        $cooperative = Cooperative::factory()->create();

        $product = $this->service->create(
            (string) $cooperative->getKey(),
            [
                'name' => 'Amlou traditionnel',
                'slug' => 'amlou-traditionnel',
                'is_active' => true,
            ],
        );

        $this->service->deactivate($product);

        $this->assertDatabaseHas('products', [
            'id' => $product->getKey(),
            'is_active' => false,
            'deleted_at' => null,
        ]);
    }

    public function test_it_soft_deletes_a_product(): void
    {
        $cooperative = Cooperative::factory()->create();

        $product = $this->service->create(
            (string) $cooperative->getKey(),
            [
                'name' => 'Huile d' . 'argan',
                'slug' => 'huile-argan',
                'is_active' => true,
            ],
        );

        $this->service->delete($product);

        $this->assertSoftDeleted('products', [
            'id' => $product->getKey(),
        ]);
    }
}
