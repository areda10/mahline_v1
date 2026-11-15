<?php

declare(strict_types=1);

namespace Tests\Framework\Domains\Media\Http;

use App\Domains\Cooperatives\Enums\CooperativeStatus;
use App\Domains\Cooperatives\Models\Cooperative;
use App\Domains\Identity\Authorization\Models\Permission;
use App\Domains\Identity\Authorization\Models\Role;
use App\Domains\Identity\Users\Models\User;
use App\Domains\Media\Models\Media;
use App\Domains\Products\Models\Product;
use App\Domains\Identity\Authentication\Http\Middleware\EnsureActiveAuthenticationSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

final class MediaControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_product_image_is_streamed_without_exposing_storage_url(): void
    {
        Storage::fake('public');

        $cooperative = Cooperative::factory()->create(['status' => CooperativeStatus::ACTIVE]);
        $product = Product::factory()->forCooperative($cooperative)->create(['is_active' => true]);
        Storage::disk('public')->put('media/test/original.jpg', 'image-content');

        $media = $product->media()->create($this->attributes('media/test/original.jpg', 'product_image', 'public', 'public', 'image/jpeg', 'jpg'));
        $response = $this->get(route('media.show', $media));

        $response->assertOk();
        $response->assertHeader('Content-Type', 'image/jpeg');
        self::assertSame('image-content', $response->streamedContent());
        self::assertStringNotContainsString('/storage/', $response->headers->get('Content-Disposition', ''));
    }

    public function test_private_media_is_not_available_on_the_public_route(): void
    {
        $cooperative = Cooperative::factory()->create();
        $product = Product::factory()->forCooperative($cooperative)->create();
        $media = $product->media()->create($this->attributes('media/private/proof.pdf', 'document', 'private', 'local', 'application/pdf', 'pdf'));

        $this->get(route('media.show', $media))->assertNotFound();
    }

    public function test_private_media_route_requires_an_authenticated_active_session(): void
    {
        $cooperative = Cooperative::factory()->create();
        $product = Product::factory()->forCooperative($cooperative)->create();
        $media = $product->media()->create($this->attributes('media/private/proof.pdf', 'document', 'private', 'local', 'application/pdf', 'pdf'));

        $this->get(route('media.private', $media))->assertRedirect('/authentication/login');
    }

    public function test_public_product_image_is_hidden_when_product_is_inactive(): void
    {
        Storage::fake('public');
        $cooperative = Cooperative::factory()->create(['status' => CooperativeStatus::ACTIVE]);
        $product = Product::factory()->forCooperative($cooperative)->create(['is_active' => false]);
        Storage::disk('public')->put('media/inactive/original.jpg', 'image-content');
        $media = $product->media()->create($this->attributes('media/inactive/original.jpg', 'product_image', 'public', 'public', 'image/jpeg', 'jpg'));

        $this->get(route('media.show', $media))->assertNotFound();
    }

    public function test_authenticated_user_without_cooperative_access_is_forbidden_from_private_product_document(): void
    {
        Storage::fake('local');
        $ownerCooperative = Cooperative::factory()->create();
        $otherCooperative = Cooperative::factory()->create();
        $product = Product::factory()->forCooperative($ownerCooperative)->create();
        Storage::disk('local')->put('media/private/proof.pdf', 'private-pdf');

        $media = $product->media()->create($this->attributes('media/private/proof.pdf', 'document', 'private', 'local', 'application/pdf', 'pdf'));
        $actor = $this->userWithPermission('cooperative_admin', 'products.view', (string) $otherCooperative->getKey());

        $this->withoutMiddleware(EnsureActiveAuthenticationSession::class)
            ->actingAs($actor)
            ->get(route('media.private', $media))
            ->assertForbidden();
    }

    public function test_root_can_stream_private_product_pdf_with_private_headers(): void
    {
        Storage::fake('local');
        $cooperative = Cooperative::factory()->create();
        $product = Product::factory()->forCooperative($cooperative)->create();
        Storage::disk('local')->put('media/private/root-proof.pdf', 'private-pdf-content');
        $media = $product->media()->create($this->attributes('media/private/root-proof.pdf', 'document', 'private', 'local', 'application/pdf', 'pdf'));
        $root = $this->userWithRole('super_admin_root', (string) $cooperative->getKey());

        $response = $this->withoutMiddleware(EnsureActiveAuthenticationSession::class)
            ->actingAs($root)
            ->get(route('media.private', $media));

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/pdf');
        $response->assertHeader('Cache-Control', 'private, no-store');
        self::assertStringContainsString('attachment', $response->headers->get('Content-Disposition', ''));
        self::assertSame('private-pdf-content', $response->streamedContent());
    }

    private function userWithPermission(string $roleSlug, string $permissionSlug, string $cooperativeId): User
    {
        $user = User::factory()->create(['cooperative_id' => $cooperativeId]);
        $role = $this->role($roleSlug);
        $permission = Permission::query()->firstOrCreate(
            ['slug' => $permissionSlug],
            ['name' => $permissionSlug, 'is_active' => true],
        );
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
        return Role::query()->firstOrCreate(['slug' => $slug], ['name' => $slug, 'is_active' => true]);
    }

    /** @return array<string, mixed> */
    private function attributes(string $path, string $category, string $visibility, string $disk, string $mimeType, string $extension): array
    {
        return [
            'collection' => $category === 'document' ? 'documents' : 'product-gallery',
            'category' => $category,
            'disk' => $disk,
            'path' => $path,
            'original_name' => 'media.'.$extension,
            'mime_type' => $mimeType,
            'extension' => $extension,
            'size' => 100,
            'visibility' => $visibility,
            'is_primary' => false,
            'sort_order' => 0,
            'metadata' => [],
        ];
    }
}
