<?php

declare(strict_types=1);

namespace Tests\Framework\Domains\Catalog\Repositories;

use App\Domains\Catalog\Models\Catalog;
use App\Domains\Catalog\Repositories\CatalogRepository;
use App\Domains\Cooperatives\Models\Cooperative;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class CatalogRepositoryTest extends TestCase
{
    use RefreshDatabase;

    private CatalogRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repository = new CatalogRepository();
    }

    public function test_it_paginates_only_catalogs_for_the_requested_cooperative(): void
    {
        $first = Cooperative::factory()->create();
        $second = Cooperative::factory()->create();

        Catalog::factory()->count(2)->create([
            'cooperative_id' => $first->getKey(),
        ]);

        Catalog::factory()->create([
            'cooperative_id' => $second->getKey(),
        ]);

        $page = $this->repository->paginateForCooperative(
            (string) $first->getKey(),
            10,
        );

        $this->assertSame(2, $page->total());
    }

    public function test_it_finds_catalog_for_its_cooperative(): void
    {
        $cooperative = Cooperative::factory()->create();

        $catalog = Catalog::factory()->create([
            'cooperative_id' => $cooperative->getKey(),
        ]);

        $found = $this->repository->findForCooperativeOrFail(
            (string) $catalog->getKey(),
            (string) $cooperative->getKey(),
        );

        $this->assertTrue($catalog->is($found));
    }

    public function test_it_does_not_find_catalog_owned_by_another_cooperative(): void
    {
        $owner = Cooperative::factory()->create();
        $other = Cooperative::factory()->create();

        $catalog = Catalog::factory()->create([
            'cooperative_id' => $owner->getKey(),
        ]);

        $this->expectException(ModelNotFoundException::class);

        $this->repository->findForCooperativeOrFail(
            (string) $catalog->getKey(),
            (string) $other->getKey(),
        );
    }

    public function test_it_creates_a_catalog(): void
    {
        $cooperative = Cooperative::factory()->create();

        $catalog = $this->repository->create([
            'cooperative_id' => $cooperative->getKey(),
            'name' => 'Catalogue Bio',
            'slug' => 'catalogue-bio',
            'is_active' => true,
        ]);

        $this->assertDatabaseHas('catalogs', [
            'id' => $catalog->getKey(),
            'slug' => 'catalogue-bio',
        ]);
    }

    public function test_it_soft_deletes_a_catalog(): void
    {
        $catalog = Catalog::factory()->create();

        $this->assertTrue($this->repository->delete($catalog));

        $this->assertSoftDeleted('catalogs', [
            'id' => $catalog->getKey(),
        ]);
    }
}
