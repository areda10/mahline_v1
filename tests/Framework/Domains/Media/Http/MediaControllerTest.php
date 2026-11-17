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

    public function test_public_cooperative_logo_and_photos_are_streamed(): void
    {
        Storage::fake('public');

        $cooperative = Cooperative::factory()->create(['status' => CooperativeStatus::ACTIVE]);

        foreach ([
            ['cooperative_logo', 'media/cooperative/logo.jpg'],
            ['cooperative_photo', 'media/cooperative/photo.jpg'],
        ] as [$category, $path]) {
            Storage::disk('public')->put($path, 'cooperative-image');

            $media = $cooperative->media()->create(array_replace(
                $this->attributes($path, $category, 'public', 'public', 'image/jpeg', 'jpg'),
                ['collection' => $category === 'cooperative_logo' ? 'logo' : 'photos'],
            ));

            $response = $this->get(route('media.show', $media));

            $response->assertOk();
            $response->assertHeader('Content-Type', 'image/jpeg');
            self::assertSame('cooperative-image', $response->streamedContent());
        }
    }

    public function test_user_can_stream_own_private_profile_image(): void
    {
        Storage::fake('local');

        $cooperative = Cooperative::factory()->create();
        $actor = $this->userWithRole('user', (string) $cooperative->getKey());
        $path = 'media/private/profile-image.jpg';
        Storage::disk('local')->put($path, 'private-profile-image');

        $media = $actor->media()->create(array_replace(
            $this->attributes($path, 'profile_image', 'private', 'local', 'image/jpeg', 'jpg'),
            ['collection' => 'profile'],
        ));

        $response = $this->withoutMiddleware(EnsureActiveAuthenticationSession::class)
            ->actingAs($actor)
            ->get(route('media.private', $media));

        $response->assertOk();
        $response->assertHeader('Content-Type', 'image/jpeg');
        $response->assertHeader('Cache-Control', 'no-store, private');
        self::assertSame('private-profile-image', $response->streamedContent());
    }

    public function test_private_media_is_not_available_on_the_public_route(): void
    {
        $cooperative = Cooperative::factory()->create();
        $product = Product::factory()->forCooperative($cooperative)->create();
        $media = $product->media()->create($this->attributes('media/private/proof.pdf', 'document', 'private', 'local', 'application/pdf', 'pdf'));

        $this->get(route('media.show', $media))->assertNotFound();
    }

    public function test_public_cooperative_image_is_hidden_when_cooperative_is_inactive(): void
    {
        Storage::fake('public');

        $cooperative = Cooperative::factory()->create(['status' => CooperativeStatus::INACTIVE]);
        $path = 'media/inactive-cooperative/logo.jpg';
        Storage::disk('public')->put($path, 'cooperative-image');

        $media = $cooperative->media()->create(array_replace(
            $this->attributes($path, 'cooperative_logo', 'public', 'public', 'image/jpeg', 'jpg'),
            ['collection' => 'logo'],
        ));

        $this->get(route('media.show', $media))->assertNotFound();
    }

    public function test_user_cannot_stream_another_users_private_profile_image(): void
    {
        Storage::fake('local');

        $cooperative = Cooperative::factory()->create();
        $owner = User::factory()->create(['cooperative_id' => $cooperative->getKey()]);
        $actor = $this->userWithRole('user', (string) $cooperative->getKey());
        $path = 'media/private/another-profile-image.jpg';
        Storage::disk('local')->put($path, 'private-profile-image');

        $media = $owner->media()->create(array_replace(
            $this->attributes($path, 'profile_image', 'private', 'local', 'image/jpeg', 'jpg'),
            ['collection' => 'profile'],
        ));

        $this->withoutMiddleware(EnsureActiveAuthenticationSession::class)
            ->actingAs($actor)
            ->get(route('media.private', $media))
            ->assertForbidden();
    }

    public function test_private_media_route_requires_an_authenticated_active_session(): void
    {
        $cooperative = Cooperative::factory()->create();
        $product = Product::factory()->forCooperative($cooperative)->create();
        $media = $product->media()->create($this->attributes('media/private/proof.pdf', 'document', 'private', 'local', 'application/pdf', 'pdf'));

        $this->get(route('media.private', $media))->assertRedirect('/authentication/login');
    }

    public function test_authenticated_user_without_active_mahline_session_cannot_access_private_media(): void
    {
        $cooperative = Cooperative::factory()->create();
        $product = Product::factory()->forCooperative($cooperative)->create();
        $media = $product->media()->create($this->attributes('media/private/no-session.pdf', 'document', 'private', 'local', 'application/pdf', 'pdf'));
        $actor = $this->userWithRole('super_admin_root', (string) $cooperative->getKey());

        $this->actingAs($actor)
            ->get(route('media.private', $media))
            ->assertRedirect(route('authentication.login'));
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

    public function test_super_admin_cannot_stream_private_document_from_unassigned_cooperative(): void
    {
        Storage::fake('local');
        $assignedCooperative = Cooperative::factory()->create();
        $unassignedCooperative = Cooperative::factory()->create();
        $product = Product::factory()->forCooperative($unassignedCooperative)->create();
        Storage::disk('local')->put('media/private/unassigned-proof.pdf', 'private-pdf');

        $media = $product->media()->create($this->attributes('media/private/unassigned-proof.pdf', 'document', 'private', 'local', 'application/pdf', 'pdf'));
        $actor = $this->userWithPermission('super_admin', 'products.view', (string) $assignedCooperative->getKey());
        $actor->cooperatives()->attach($assignedCooperative->getKey());

        $this->withoutMiddleware(EnsureActiveAuthenticationSession::class)
            ->actingAs($actor)
            ->get(route('media.private', $media))
            ->assertForbidden();
    }

    public function test_user_cannot_stream_another_users_private_document(): void
    {
        Storage::fake('local');
        $cooperative = Cooperative::factory()->create();
        $owner = User::factory()->create(['cooperative_id' => $cooperative->getKey()]);
        Storage::disk('local')->put('media/private/other-user-proof.pdf', 'private-user-pdf');

        $media = $owner->media()->create($this->attributes('media/private/other-user-proof.pdf', 'document', 'private', 'local', 'application/pdf', 'pdf'));
        $actor = $this->userWithRole('user', (string) $cooperative->getKey());

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
        $response->assertHeader('Cache-Control', 'no-store, private');
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
