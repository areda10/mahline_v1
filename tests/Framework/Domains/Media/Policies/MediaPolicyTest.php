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

    public function test_policy_rejects_media_category_that_does_not_match_its_owner(): void
    {
        $cooperative = Cooperative::factory()->create();
        $actor = $this->userWithPermission(
            'cooperative_admin',
            'products.view',
            (string) $cooperative->getKey(),
        );
        $product = Product::factory()->forCooperative($cooperative)->create();
        $media = $product->media()->create(array_replace(
            $this->documentAttributes('products/private/invalid-profile.jpg'),
            [
                'category' => 'profile_image',
                'mime_type' => 'image/jpeg',
                'extension' => 'jpg',
            ],
        ));

        self::assertFalse(Gate::forUser($actor)->allows('view', $media));
        self::assertFalse(Gate::forUser($actor)->allows('update', $media));
    }

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

    public function test_super_admin_can_view_user_private_documents_only_for_assigned_cooperatives(): void
    {
        $assigned = Cooperative::factory()->create();
        $unassigned = Cooperative::factory()->create();
        $actor = $this->userWithPermission('super_admin', 'users.view', (string) $assigned->getKey());
        $actor->cooperatives()->attach($assigned->getKey());

        $assignedOwner = User::factory()->create(['cooperative_id' => $assigned->getKey()]);
        $unassignedOwner = User::factory()->create(['cooperative_id' => $unassigned->getKey()]);
        $assignedDocument = $assignedOwner->media()->create($this->documentAttributes('users/assigned/proof.pdf'));
        $unassignedDocument = $unassignedOwner->media()->create($this->documentAttributes('users/unassigned/proof.pdf'));

        self::assertTrue(Gate::forUser($actor)->allows('view', $assignedDocument));
        self::assertFalse(Gate::forUser($actor)->allows('view', $unassignedDocument));
    }

    public function test_root_can_view_user_private_documents_only_with_users_view_permission(): void
    {
        $cooperative = Cooperative::factory()->create();
        $owner = User::factory()->create(['cooperative_id' => $cooperative->getKey()]);
        $document = $owner->media()->create($this->documentAttributes('users/root-check/proof.pdf'));

        $rootRole = $this->role('super_admin_root');
        $usersViewPermission = Permission::query()->firstOrCreate(
            ['slug' => 'users.view'],
            ['name' => 'users.view', 'is_active' => true],
        );

        // The permission belongs to the shared role, so assert the negative case
        // before granting it to that role.
        $rootRole->permissions()->detach($usersViewPermission->getKey());

        $rootWithoutPermission = User::factory()->create([
            'cooperative_id' => $cooperative->getKey(),
        ]);
        $rootWithoutPermission->roles()->attach($rootRole->getKey());

        self::assertFalse(Gate::forUser($rootWithoutPermission->refresh())->allows('view', $document));

        $rootRole->permissions()->syncWithoutDetaching([$usersViewPermission->getKey()]);
        $rootWithPermission = User::factory()->create([
            'cooperative_id' => $cooperative->getKey(),
        ]);
        $rootWithPermission->roles()->attach($rootRole->getKey());

        self::assertTrue(Gate::forUser($rootWithPermission->refresh())->allows('view', $document));
    }

    public function test_only_root_can_view_private_documents_after_owner_soft_delete(): void
    {
        $cooperative = Cooperative::factory()->create();
        $product = Product::factory()->forCooperative($cooperative)->create();
        $document = $product->media()->create($this->documentAttributes('products/deleted-owner/proof.pdf'));
        $product->delete();

        $root = $this->userWithRole('super_admin_root', (string) $cooperative->getKey());
        $cooperativeAdmin = $this->userWithPermission(
            'cooperative_admin',
            'products.view',
            (string) $cooperative->getKey(),
        );

        self::assertTrue(Gate::forUser($root)->allows('view', $document));
        self::assertFalse(Gate::forUser($cooperativeAdmin)->allows('view', $document));
        self::assertFalse(Gate::forUser($cooperativeAdmin)->allows('update', $document));
    }

    public function test_pending_archived_or_emergency_locked_users_cannot_view_private_media(): void
    {
        $cooperative = Cooperative::factory()->create();
        $product = Product::factory()->forCooperative($cooperative)->create();
        $media = $product->media()->create($this->documentAttributes('products/private/proof.pdf'));

        $pending = $this->userWithPermission('cooperative_admin', 'products.view', (string) $cooperative->getKey(), [
            'status' => UserStatus::Pending,
        ]);
        $archived = $this->userWithPermission('cooperative_admin', 'products.view', (string) $cooperative->getKey(), [
            'status' => UserStatus::Archived,
        ]);
        $emergencyLocked = $this->userWithPermission('cooperative_admin', 'products.view', (string) $cooperative->getKey());
        $emergencyLocked->forceFill(['emergency_locked_at' => now()])->save();

        self::assertFalse(Gate::forUser($pending)->allows('view', $media));
        self::assertFalse(Gate::forUser($archived)->allows('view', $media));
        self::assertFalse(Gate::forUser($emergencyLocked)->allows('view', $media));
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
        $suspended = $this->userWithPermission('cooperative_admin', 'products.view', (string) $cooperative->getKey(), [
            'status' => UserStatus::Suspended,
        ]);

        self::assertFalse(Gate::forUser($unverified)->allows('view', $media));
        self::assertFalse(Gate::forUser($inactive)->allows('view', $media));
        self::assertFalse(Gate::forUser($suspended)->allows('view', $media));
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
        $permission = Permission::query()->firstOrCreate(
            ['slug' => $permissionSlug],
            ['name' => $permissionSlug, 'is_active' => true],
        );
        $role->permissions()->syncWithoutDetaching([$permission->getKey()]);
        $user->roles()->syncWithoutDetaching([$role->getKey()]);

        return $user->refresh();
    }

    private function userWithRole(string $roleSlug, string $cooperativeId): User
    {
        $user = User::factory()->create(['cooperative_id' => $cooperativeId]);
        $user->roles()->syncWithoutDetaching([$this->role($roleSlug)->getKey()]);

        return $user->refresh();
    }

    private function role(string $slug): Role
    {
        return Role::query()->firstOrCreate(
            ['slug' => $slug],
            ['name' => $slug, 'is_active' => true],
        );
    }
}
