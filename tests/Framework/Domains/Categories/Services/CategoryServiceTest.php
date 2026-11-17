<?php

declare(strict_types=1);

namespace Tests\Framework\Domains\Categories\Services;

use App\Domains\Audit\Models\AuditEvent;
use App\Domains\Categories\Models\Category;
use App\Domains\Categories\Services\CategoryService;
use App\Domains\Cooperatives\Models\Cooperative;
use App\Domains\Identity\Users\Models\User;
use App\Domains\Products\Models\Product;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

final class CategoryServiceTest extends TestCase
{
    use RefreshDatabase;

    private CategoryService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(CategoryService::class);
    }

    public function test_service_creates_category(): void
    {
        $category = $this->service->create([
            'name' => 'Bien-être',
            'slug' => 'bien-etre',
            'is_active' => true,
            'sort_order' => 10,
        ]);

        $this->assertDatabaseHas('categories', [
            'id' => $category->getKey(),
            'slug' => 'bien-etre',
        ]);
    }

    public function test_category_creation_and_deletion_are_audited_with_actor(): void
    {
        $actor = User::factory()->create();
        $category = $this->service->createGlobal([
            'name' => 'Catégorie auditée',
            'slug' => 'categorie-auditee',
        ], $actor);

        self::assertSame((string) $actor->getKey(), $category->fresh()->created_by);

        $this->service->delete($category, $actor);

        self::assertSoftDeleted('categories', ['id' => $category->getKey()]);
        self::assertSame((string) $actor->getKey(), Category::withTrashed()->findOrFail($category->getKey())->deleted_by);
        self::assertSame(2, AuditEvent::query()
            ->where('subject_type', Category::class)
            ->where('subject_id', $category->getKey())
            ->count());
    }

    public function test_service_creates_child_category(): void
    {
        $parent = $this->createCategory([
            'name' => 'Alimentation',
            'slug' => 'alimentation',
        ]);

        $child = $this->service->create([
            'name' => 'Miels',
            'slug' => 'miels',
            'parent_id' => $parent->getKey(),
        ]);

        $this->assertSame(
            $parent->getKey(),
            $child->parent_id,
        );
    }

    public function test_service_rejects_nonexistent_parent(): void
    {
        $this->expectException(ModelNotFoundException::class);

        $this->service->create([
            'name' => 'Miels',
            'slug' => 'miels',
            'parent_id' => '01J00000000000000000000000',
        ]);
    }

    public function test_service_rejects_category_as_its_own_parent(): void
    {
        $category = $this->createCategory();

        try {
            $this->service->update($category, [
                'parent_id' => $category->getKey(),
            ]);

            $this->fail(
                'Une catégorie ne doit pas être son propre parent.',
            );
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey(
                'parent_id',
                $exception->errors(),
            );
        }
    }

    public function test_service_rejects_parent_that_is_a_descendant(): void
    {
        $parent = $this->createCategory([
            'name' => 'Alimentation',
            'slug' => 'alimentation',
        ]);

        $child = $this->createCategory([
            'name' => 'Miels',
            'slug' => 'miels',
            'parent_id' => $parent->getKey(),
        ]);

        try {
            $this->service->update($parent, [
                'parent_id' => $child->getKey(),
            ]);

            $this->fail(
                'Le déplacement doit être refusé pour éviter un cycle.',
            );
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey(
                'parent_id',
                $exception->errors(),
            );
        }
    }

    public function test_service_updates_category(): void
    {
        $category = $this->createCategory();

        $updated = $this->service->update($category, [
            'name' => 'Épices',
            'slug' => 'epices',
        ]);

        $this->assertSame('Épices', $updated->name);
        $this->assertSame('epices', $updated->slug);
    }

    public function test_service_deletes_global_parent_and_global_children(): void
    {
        $parent = $this->createCategory([
            'name' => 'Alimentation',
            'slug' => 'alimentation',
        ]);

        $child = $this->createCategory([
            'name' => 'Miels',
            'slug' => 'miels',
            'parent_id' => $parent->getKey(),
        ]);

        $this->assertNull($parent->cooperative_id);
        $this->assertNull($child->cooperative_id);

        $this->service->delete($parent);

        $this->assertSoftDeleted('categories', [
            'id' => $parent->getKey(),
        ]);

        $this->assertSoftDeleted('categories', [
            'id' => $child->getKey(),
        ]);
    }

    public function test_service_soft_deletes_category_without_children(): void
    {
        $category = $this->createCategory();

        $this->service->delete($category);

        $this->assertSoftDeleted('categories', [
            'id' => $category->getKey(),
        ]);
    }


    public function test_deleting_global_branch_detaches_local_children_and_preserves_their_subtree(): void
    {
        $cooperative = Cooperative::factory()->create();
        $global = $this->createCategory(['name' => 'Global', 'slug' => 'global']);
        $local = $this->service->createForCooperative((string) $cooperative->getKey(), [
            'name' => 'Local', 'slug' => 'local', 'parent_id' => $global->getKey(),
        ]);
        $localChild = $this->service->createForCooperative((string) $cooperative->getKey(), [
            'name' => 'Sous-catégorie locale', 'slug' => 'sous-categorie-locale', 'parent_id' => $local->getKey(),
        ]);

        $this->service->delete($global);

        $this->assertSoftDeleted('categories', ['id' => $global->getKey()]);
        self::assertNull($local->fresh()->parent_id);
        self::assertSame($local->getKey(), $localChild->fresh()->parent_id);
        self::assertNull($local->fresh()->deleted_at);
        self::assertNull($localChild->fresh()->deleted_at);
    }

    public function test_deleting_last_active_category_deactivates_but_does_not_delete_product(): void
    {
        $cooperative = Cooperative::factory()->create();
        $category = Category::factory()->forCooperative($cooperative)->create();
        $product = Product::factory()->forCooperative($cooperative)->create();
        $product->categories()->attach($category->getKey());
        $actor = User::factory()->create();

        $this->service->delete($category, $actor);

        $this->assertSoftDeleted('categories', ['id' => $category->getKey()]);
        $this->assertDatabaseHas('products', [
            'id' => $product->getKey(), 'is_active' => false, 'deleted_at' => null,
        ]);
        $this->assertDatabaseHas('audit_events', [
            'event_key' => 'product.deactivated_no_active_categories',
            'subject_type' => Product::class,
            'subject_id' => $product->getKey(),
            'actor_id' => $actor->getKey(),
        ]);
        $this->assertDatabaseMissing('category_product', [
            'category_id' => $category->getKey(), 'product_id' => $product->getKey(),
        ]);
    }

    public function test_product_remains_active_when_another_active_category_is_preserved(): void
    {
        $cooperative = Cooperative::factory()->create();
        $first = Category::factory()->forCooperative($cooperative)->create();
        $second = Category::factory()->forCooperative($cooperative)->create();
        $product = Product::factory()->forCooperative($cooperative)->create();
        $product->categories()->attach([$first->getKey(), $second->getKey()]);

        $this->service->delete($first);

        self::assertTrue($product->fresh()->is_active);
        self::assertTrue($product->fresh()->categories()->whereKey($second->getKey())->exists());
    }

    private function createCategory(array $attributes = []): Category
    {
        return Category::query()->create([
            'name' => 'Catégorie test',
            'slug' => 'categorie-test',
            ...$attributes,
        ]);
    }
}
