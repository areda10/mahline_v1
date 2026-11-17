<?php

declare(strict_types=1);

namespace Tests\Framework\Domains\Catalog\Services;

use App\Domains\Catalog\Models\Catalog;
use App\Domains\Catalog\Services\CatalogPdfService;
use App\Domains\Products\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class CatalogPdfServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_generates_a_pdf_for_active_catalog_products(): void
    {
        $catalog = Catalog::factory()->create();
        $product = Product::factory()->forCooperative((string) $catalog->cooperative_id)->create([
            'name' => 'Miel du Maroc',
        ]);
        $catalog->products()->attach($product->getKey());

        $pdf = app(CatalogPdfService::class)->generate($catalog);

        self::assertStringStartsWith('%PDF-', $pdf);
    }
}
