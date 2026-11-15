<?php

declare(strict_types=1);

namespace Tests\Framework\Domains\Media\Policies;

use App\Domains\Cooperatives\Models\Cooperative;
use App\Domains\Identity\Authorization\Models\Permission;
use App\Domains\Identity\Authorization\Models\Role;
use App\Domains\Identity\Enums\UserStatus;
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
        $actor = $this->userWithPermission('cooperative_admin', 'products.update', (string) $ownCooperative->getKey());

        $ownProduct = Product::factory()->forCooperative($ownCooperative)->create();
        $otherProduct = Product::factory()->forCooperative($otherCooperative)->create();

        self::assertTrue(Gate::forUser($actor)->allows('upload', [new Media, $ownProduct, 'product_image']));
        self::assertFalse(Gate::forUser($actor)->allows('upload', [new Media, $otherProduct, 'product_image']));
    }

    public function test_user_role_cannot_upload_product_media(): void
    {
        $cooperative = Cooperative::factory()->create();
        $actor = $this->userWithPermission('user', 'products.view', (string) $cooperative->getKey());
        $product = Product::factory()->forCooperative($cooperative)->create();

        self::assertFalse(Gate::forUser($actor)->allows('upload', [new Media, $product, 'product_image']));
    }

    public function test_users_can_manage_only_their_own_profile_media(): void
    {
        $cooperative = Cooperative::factory()->create();
        $actor = $this->userWithPermission('user', 'products.view', (string) $cooperative->getKey());
        $otherUser = User::factory()->create(['cooperative_id' => $cooperative->getKey()]);

        self::assertTrue(Gate::forUser($actor)->allows('upload', [new Media, $actor, 'profile_image']));
        self::assertFalse(Gate::forUser($actor)->allows('upload', [new Media, $otherUser, 'profile_image']));
    }

    public function test_super_admin_can_view_private_product_documents_only_for_assigned_cooperatives(): void
    {
        $assigned = Cooperative::factory()->create();
        $unassigned = Cooperative::factory()->create();
        $actor = $this->userWithPermission('super_admin', 'products.view', (string) $assigned->getKey());
        $actor->cooperatives()->attach($assigned->getKey());

        $assignedMedia = $this->privateProductDocument($assigned);
        $unassignedMedia = $this->privateProductDocument($unassigned);

        self::assertTrue(Gate::forUser($actor)->allows('view', $assignedMedia));
        self::assertFalse(Gate::forUser($actor)->allows('view', $unassignedMedia));
    }

    public function test_cooperative_admin_cannot_view_private_documents_outside_its_cooperative(): void
    {
        $own = Cooperative::factory()->create();
        $other = Cooperative::factory()->create();
        $actor = $this->userWithPermission('cooperative_admin', 'products.view', (string) $own->getKey());

        self::assertTrue(Gate::forUser($actor)->allows('view', $this->privateProductDocument($own)));
        self::assertFalse(Gate::forUser($actor)->allows('view', $this->privateProductDocument($other)));
    }

    public function test_user_can_view_own_private_documents_but_not_another_users_documents(): void
    {
        $cooperative = Cooperative::factory()->create();
        $actor = $this->userWithPermission('user', 'products.view', (string) $cooperative->getKey());
        $otherUser = User::factory()->create(['cooperative_id' => $cooperative->getKey()]);

        $ownDocument = $actor->media()->create($this->documentAttributes('users/own/proof.pdf'));
        $otherDocument = $otherUser->media()->create($this->documentAttributes('users/other/proof.pdf'));

        self::assertTrue(Gate::forUser($actor)->allows('view', $ownDocument));
        self::assertFalse(Gate::forUser($actor)->allows('view', $otherDocument));
    }

    public function test_root_can_view_private_product_documents(): void
    {
        $cooperative = Cooperative::factory()->create();
        $root = $this->userWithRole('super_admin_root', (string) $cooperative->getKey());

        self::assertTrue(Gate::forUser($root)->allows('view', $this->privateProductDocument($cooperative)));
    }

    public function test_unverified_or_inactive_users_cannot_view_private_media(): void
    {
        $cooperative = Cooperative::factory()->create();
        $product = Product::factory()->forCooperative($cooperative)->create();
        $media = $product->media()->create($this->documentAttributes('products/private/proof.pdf'));

        $unverified = $this->userWithPermission('cooperative_admin', 'products.view', (string) $cooperative->getKey(), [
            'email_verified_at' => null,
        ]);
        $inactive = $this->userWithPermission('cooperative_admin', 'products.view', (string) $cooperative->getKey(), [
            'status' => UserStatus::Inactive,
        ]);

        self::assertFalse(Gate::forUser($unverified)->allows('view', $media));
        self::assertFalse(Gate::forUser($inactive)->allows('view', $media));
    }

    private function privateProductDocument(Cooperative $cooperative): Media
    {
        $product = Product::factory()->forCooperative($cooperative)->create();

        return $product->media()->create($this->documentAttributes('products/'.$product->getKey().'/proof.pdf'));
    }

    /** @return array<string, mixed> */
    private function documentAttributes(string $path): array
    {
        return [
            'collection' => 'documents',
            'category' => 'document',
            'disk' => 'local',
            'path' => $path,
            'original_name' => 'proof.pdf',
            'mime_type' => 'application/pdf',
            'extension' => 'pdf',
            'size' => 100,
            'visibility' => 'private',
            'is_primary' => false,
            'sort_order' => 0,
            'metadata' => [],
        ];
    }

    /** @param array<string, mixed> $attributes */
    private function userWithPermission(string $roleSlug, string $permissionSlug, string $cooperativeId, array $attributes = []): User
    {
        $user = User::factory()->create(array_merge(['cooperative_id' => $cooperativeId], $attributes));
        $role = $this->role($roleSlug);
        $permission = Permission::query()->create([
            'name' => $permissionSlug,
            'slug' => $permissionSlug,
            'is_active' => true,
        ]);
        $role->permissions()->attach($permission->getKey());
        $user->roles()->attach($role->getKey());

        return $user->refresh();
    }

    private function userWithRole(string $roleSlug, string $cooperativeId): User
    {
        $user = User::factory()->create(['cooperative_id' => $cooperativeId]);
        $user->roles()->attach($this->role($roleSlug)->getKey());

        return $user->refresh();
    }

    private function role(string $slug): Role
    {
        return Role::query()->create([
            'name' => $slug,
            'slug' => $slug,
            'is_active' => true,
        ]);
    }
}
