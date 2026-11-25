<?php

declare(strict_types=1);

namespace Tests\Framework\Domains\Products\Services;

use App\Domains\Audit\Models\AuditEvent;
use App\Domains\Categories\Models\Category;
use App\Domains\Cooperatives\Models\Cooperative;
use App\Domains\Identity\Users\Models\User;
use App\Domains\Products\Models\Product;
use App\Domains\Products\Models\ProductUnit;
use Database\Seeders\ProductUnitSeeder;
use App\Domains\Products\Repositories\ProductRepository;
use App\Domains\Products\Services\ProductService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

final class ProductServiceTest extends TestCase
{
    use RefreshDatabase;

    private ProductService $service;

    private string $unitId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(ProductUnitSeeder::class);
        $this->unitId = (string) ProductUnit::query()->where('code', 'kg')->value('id');
        $this->service = app(ProductService::class);
    }

    public function test_it_creates_a_product_for_the_given_cooperative(): void
    {
        $cooperative = Cooperative::factory()->create();
        $category = Category::factory()
            ->forCooperative($cooperative)
            ->create();
        $actor = User::factory()->create();

        $product = $this->service->create(
            (string) $cooperative->getKey(),
            [
                'name' => 'Miel naturel',
                'unit_id' => $this->unitId,
                'slug' => 'miel-naturel',
                'description' => 'Miel produit au Maroc.',
                'is_active' => true,
                'category_ids' => [$category->getKey()],
            ],
            $actor,
        );

        $this->assertSame(
            $cooperative->getKey(),
            $product->cooperative_id,
        );

        $this->assertDatabaseHas('products', [
            'id' => $product->getKey(),
            'cooperative_id' => $cooperative->getKey(),
            'name' => 'Miel naturel',
                'unit_id' => $this->unitId,
            'is_active' => true,
        ]);

        self::assertSame((string) $actor->getKey(), $product->fresh()->created_by);
        self::assertDatabaseHas('audit_events', [
            'event_key' => 'product.created',
            'subject_type' => Product::class,
            'subject_id' => $product->getKey(),
            'actor_id' => $actor->getKey(),
        ]);
        self::assertInstanceOf(AuditEvent::class, AuditEvent::query()
            ->where('subject_id', $product->getKey())
            ->firstOrFail());
    }


    public function test_it_creates_initial_variants_for_an_inactive_parent_product(): void
    {
        $cooperative = Cooperative::factory()->create();

        $product = $this->service->create((string) $cooperative->getKey(), [
            'product_type' => 'variant',
            'name' => 'Produit en préparation',
            'is_active' => false,
            'category_ids' => [],
            'variants' => [[
                'name' => 'Format 250 g',
                'unit_id' => $this->unitId,
                'price' => '15.00',
                'is_active' => true,
            ]],
        ]);

        $this->assertFalse($product->is_active);
        $this->assertCount(1, $product->variants);
        $this->assertSame('15.00', $product->variants->first()->price);
    }

    public function test_it_accepts_piece_unit_for_a_simple_product(): void
    {
        $cooperative = Cooperative::factory()->create();
        $pieceUnit = ProductUnit::query()->where('code', 'u')->firstOrFail();

        $product = $this->service->create(
            (string) $cooperative->getKey(),
            [
                'name' => 'Produit vendu à la pièce',
                'unit_id' => (string) $pieceUnit->getKey(),
            ],
        );

        $this->assertSame('u', $product->unit->code);
    }

    public function test_it_rejects_product_creation_without_a_registered_unit(): void
    {
        $cooperative = Cooperative::factory()->create();

        $this->expectException(ValidationException::class);

        $this->service->create(
            (string) $cooperative->getKey(),
            ['name' => 'Produit sans unité'],
        );
    }

    public function test_it_does_not_return_another_cooperatives_product(): void
    {
        $firstCooperative = Cooperative::factory()->create();
        $secondCooperative = Cooperative::factory()->create();

        $product = $this->service->create(
            (string) $firstCooperative->getKey(),
            [
                'name' => 'Miel naturel',
                'unit_id' => $this->unitId,
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
            ProductRepository::class,
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
                'unit_id' => $this->unitId,
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

    public function test_restoring_product_without_an_active_category_keeps_it_inactive(): void
    {
        $cooperative = Cooperative::factory()->create();
        $product = $this->service->create((string) $cooperative->getKey(), [
            'name' => 'Produit sans catégorie',
            'unit_id' => $this->unitId,
            'is_active' => true,
            'category_ids' => [],
        ]);

        $this->assertFalse($product->is_active);
        $this->service->delete($product);
        $restored = $this->service->restore($product);

        $this->assertTrue($restored);
        $this->assertDatabaseHas('products', [
            'id' => $product->getKey(),
            'deleted_at' => null,
            'is_active' => false,
        ]);
    }

    public function test_it_soft_deletes_a_product(): void
    {
        $cooperative = Cooperative::factory()->create();

        $product = $this->service->create(
            (string) $cooperative->getKey(),
            [
                'name' => 'Huile d'.'argan',
                'unit_id' => $this->unitId,
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
