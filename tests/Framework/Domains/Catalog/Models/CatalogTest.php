<?php

declare(strict_types=1);

namespace Tests\Framework\Domains\Catalog\Models;

use App\Domains\Catalog\Models\Catalog;
use App\Domains\Cooperatives\Models\Cooperative;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class CatalogTest extends TestCase
{
    use RefreshDatabase;

    public function test_catalog_belongs_to_a_cooperative(): void
    {
        $cooperative = Cooperative::factory()->create();

        $catalog = Catalog::factory()->create([
            'cooperative_id' => $cooperative->getKey(),
        ]);

        $this->assertTrue(
            $catalog->cooperative->is($cooperative)
        );
    }

    public function test_catalog_can_be_inactive(): void
    {
        $catalog = Catalog::factory()->inactive()->create();

        $this->assertFalse($catalog->is_active);
    }

    public function test_active_scope_returns_only_active_catalogs(): void
    {
        Catalog::factory()->create(['is_active' => true]);
        Catalog::factory()->inactive()->create();

        $this->assertSame(
            1,
            Catalog::query()->active()->count()
        );
    }

    public function test_catalog_can_be_soft_deleted(): void
    {
        $catalog = Catalog::factory()->create();

        $catalog->delete();

        $this->assertSoftDeleted('catalogs', [
            'id' => $catalog->getKey(),
        ]);
    }
}
