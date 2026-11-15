<?php

declare(strict_types=1);

namespace Tests\Framework\Domains\Media\Policies;

use App\Domains\Cooperatives\Models\Cooperative;
use App\Domains\Identity\Authorization\Models\Permission;
use App\Domains\Identity\Authorization\Models\Role;
use App\Domains\Identity\Users\Models\User;
use App\Domains\Media\Models\Media;
use App\Domains\Products\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

final class MediaPolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_cooperative_admin_can_manage_media_for_its_own_products_only(): void
    {
        $ownCooperative = Cooperative::factory()->create();
        $otherCooperative = Cooperative::factory()->create();
        $actor = $this->userWithPermission(
            'cooperative_admin',
            'products.update',
            (string) $ownCooperative->getKey(),
        );

        $ownProduct = Product::factory()->forCooperative($ownCooperative)->create();
        $otherProduct = Product::factory()->forCooperative($otherCooperative)->create();

        self::assertTrue(Gate::forUser($actor)->allows('upload', [
            new Media,
            $ownProduct,
            'product_image',
        ]));

        self::assertFalse(Gate::forUser($actor)->allows('upload', [
            new Media,
            $otherProduct,
            'product_image',
        ]));
    }

    public function test_user_role_cannot_upload_product_media(): void
    {
        $cooperative = Cooperative::factory()->create();
        $actor = $this->userWithPermission(
            'user',
            'products.view',
            (string) $cooperative->getKey(),
        );
        $product = Product::factory()->forCooperative($cooperative)->create();

        self::assertFalse(Gate::forUser($actor)->allows('upload', [
            new Media,
            $product,
            'product_image',
        ]));
    }

    public function test_users_can_manage_only_their_own_profile_media(): void
    {
        $cooperative = Cooperative::factory()->create();
        $actor = $this->userWithPermission(
            'user',
            'products.view',
            (string) $cooperative->getKey(),
        );
        $otherUser = User::factory()->create([
            'cooperative_id' => $cooperative->getKey(),
        ]);

        self::assertTrue(Gate::forUser($actor)->allows('upload', [
            new Media,
            $actor,
            'profile_image',
        ]));

        self::assertFalse(Gate::forUser($actor)->allows('upload', [
            new Media,
            $otherUser,
            'profile_image',
        ]));
    }

    private function userWithPermission(
        string $roleSlug,
        string $permissionSlug,
        string $cooperativeId,
    ): User {
        $user = User::factory()->create([
            'cooperative_id' => $cooperativeId,
        ]);

        $role = Role::query()->create([
            'name' => $roleSlug,
            'slug' => $roleSlug,
            'is_active' => true,
        ]);

        $permission = Permission::query()->create([
            'name' => $permissionSlug,
            'slug' => $permissionSlug,
            'is_active' => true,
        ]);

        $role->permissions()->attach($permission->getKey());
        $user->roles()->attach($role->getKey());

        return $user->refresh();
    }
}
