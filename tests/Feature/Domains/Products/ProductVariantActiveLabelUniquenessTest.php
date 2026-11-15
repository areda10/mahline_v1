<?php

declare(strict_types=1);

namespace Tests\\Feature\\Domains\\Products;

use App\\Domains\\Products\\Models\\Product;
use App\\Domains\\Products\\Models\\ProductVariant;
use Illuminate\\Database\\QueryException;
use Illuminate\\Foundation\\Testing\\RefreshDatabase;
use Tests\\TestCase;

final class ProductVariantActiveLabelUniquenessTest extends TestCase
{
    use RefreshDatabase;

    public function test_inactive_variants_can_share_a_normalized_label(): void
    {
        $product = Product::factory()->create();

        ProductVariant::factory()->for($product)->inactive()->create([
            'label' => 'Format 250 g',
            'normalized_label' => 'format 250 g',
        ]);

        ProductVariant::factory()->for($product)->inactive()->create([
            'label' => 'FORMAT 250 G',
            'normalized_label' => 'format 250 g',
        ]);

        $this->assertDatabaseCount('product_variants', 2);
    }

    public function test_inactive_variant_does_not_block_an_active_variant_label(): void
    {
        $product = Product::factory()->create();

        ProductVariant::factory()->for($product)->inactive()->create([
            'label' => 'Format 250 g',
            'normalized_label' => 'format 250 g',
        ]);

        ProductVariant::factory()->for($product)->create([
            'label' => 'FORMAT 250 G',
            'normalized_label' => 'format 250 g',
            'is_active' => true,
        ]);

        $this->assertDatabaseCount('product_variants', 2);
    }

    public function test_database_rejects_duplicate_active_variant_labels(): void
    {
        $product = Product::factory()->create();

        ProductVariant::factory()->for($product)->create([
            'label' => 'Format 250 g',
            'normalized_label' => 'format 250 g',
            'is_active' => true,
        ]);

        $this->expectException(QueryException::class);

        ProductVariant::factory()->for($product)->create([
            'label' => 'FORMAT 250 G',
            'normalized_label' => 'format 250 g',
            'is_active' => true,
        ]);
    }

    public function test_soft_deleted_variant_does_not_block_label_reuse(): void
    {
        $product = Product::factory()->create();

        $variant = ProductVariant::factory()->for($product)->create([
            'label' => 'Format 250 g',
            'normalized_label' => 'format 250 g',
            'is_active' => true,
        ]);

        $variant->delete();

        ProductVariant::factory()->for($product)->create([
            'label' => 'FORMAT 250 G',
            'normalized_label' => 'format 250 g',
            'is_active' => true,
        ]);

        $this->assertDatabaseCount('product_variants', 2);
    }
}
