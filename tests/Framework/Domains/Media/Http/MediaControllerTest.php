<?php

declare(strict_types=1);

namespace Tests\Framework\Domains\Media\Http;

use App\Domains\Catalog\Models\Catalog;
use App\Domains\Cooperatives\Enums\CooperativeStatus;
use App\Domains\Cooperatives\Models\Cooperative;
use App\Domains\Identity\Authentication\Http\Middleware\EnsureActiveAuthenticationSession;
use App\Domains\Identity\Authorization\Models\Permission;
use App\Domains\Identity\Authorization\Models\Role;
use App\Domains\Identity\Users\Models\User;
use App\Domains\Media\Models\Media;
use App\Domains\Media\Services\MediaService;
use App\Domains\Media\Services\MediaIncidentService;
use App\Domains\Products\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

final class MediaControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Match the same-origin Fetch Metadata header sent by browsers.
        $this->withHeader('Sec-Fetch-Site', 'same-origin');
    }

    public function test_public_product_image_is_streamed_without_exposing_storage_url(): void
    {
        Storage::fake('public');

        $cooperative = Cooperative::factory()->create(['status' => CooperativeStatus::ACTIVE]);
        $product = Product::factory()->forCooperative($cooperative)->create(['is_active' => true]);
        $catalog = Catalog::factory()->create([
            'cooperative_id' => $cooperative->getKey(),
            'is_active' => true,
        ]);
        $product->catalogs()->attach($catalog->getKey());
        Storage::disk('public')->put('media/test/original.jpg', 'image-content');

        $media = $product->media()->create($this->attributes('media/test/original.jpg', 'product_image', 'public', 'public', 'image/jpeg', 'jpg'));
        $response = $this->get(route('media.show', $media));

        $response->assertOk();
        $response->assertHeader('Content-Type', 'image/jpeg');
        $cacheControl = array_map('trim', explode(',', $response->headers->get('Cache-Control', '')));
        self::assertContains('public', $cacheControl);
        self::assertContains('no-cache', $cacheControl);
        self::assertContains('must-revalidate', $cacheControl);
        self::assertNotContains('private', $cacheControl);
        self::assertSame('image-content', $response->streamedContent());
        self::assertStringNotContainsString('/storage/', $response->headers->get('Content-Disposition', ''));
    }

    public function test_public_media_with_a_category_that_does_not_match_its_owner_is_hidden(): void
    {
        Storage::fake('public');

        $cooperative = Cooperative::factory()->create(['status' => CooperativeStatus::ACTIVE]);
        $product = Product::factory()->forCooperative($cooperative)->create(['is_active' => true]);
        $path = 'media/category-mismatch/original.jpg';
        Storage::disk('public')->put($path, 'image-content');

        $media = $product->media()->create(
            $this->attributes($path, 'cooperative_logo', 'public', 'public', 'image/jpeg', 'jpg'),
        );

        $this->get(route('media.show', $media))->assertNotFound();
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

        $cooperative = Cooperative::factory()->active()->create();
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

    public function test_root_can_stream_private_document_after_owner_soft_delete_and_access_is_audited(): void
    {
        Storage::fake('local');

        $cooperative = Cooperative::factory()->create();
        $product = Product::factory()->forCooperative($cooperative)->create();
        Storage::disk('local')->put('media/private/deleted-owner-proof.pdf', 'retained-private-pdf');
        $media = $product->media()->create($this->attributes(
            'media/private/deleted-owner-proof.pdf',
            'document',
            'private',
            'local',
            'application/pdf',
            'pdf',
        ));
        $product->delete();

        $root = $this->userWithRole('super_admin_root', (string) $cooperative->getKey());
        $response = $this->withoutMiddleware(EnsureActiveAuthenticationSession::class)
            ->actingAs($root)
            ->get(route('media.private', $media));

        $response->assertOk();
        self::assertSame('retained-private-pdf', $response->streamedContent());
        self::assertDatabaseHas('audit_events', [
            'event_key' => 'media.private_accessed_deleted_owner',
            'subject_type' => Media::class,
            'subject_id' => $media->getKey(),
            'actor_id' => $root->getKey(),
        ]);

        $cooperativeAdmin = $this->userWithPermission(
            'cooperative_admin',
            'products.view',
            (string) $cooperative->getKey(),
        );
        $this->withoutMiddleware(EnsureActiveAuthenticationSession::class)
            ->actingAs($cooperativeAdmin)
            ->get(route('media.private', $media))
            ->assertForbidden();
    }

    public function test_public_product_image_is_hidden_after_product_soft_delete(): void
    {
        Storage::fake('public');

        $cooperative = Cooperative::factory()->create(['status' => CooperativeStatus::ACTIVE]);
        $product = Product::factory()->forCooperative($cooperative)->create(['is_active' => true]);
        $catalog = Catalog::factory()->create([
            'cooperative_id' => $cooperative->getKey(),
            'is_active' => true,
        ]);
        $product->catalogs()->attach($catalog->getKey());
        Storage::disk('public')->put('media/deleted-product/original.jpg', 'image-content');
        $media = $product->media()->create($this->attributes(
            'media/deleted-product/original.jpg',
            'product_image',
            'public',
            'public',
            'image/jpeg',
            'jpg',
        ));
        $product->delete();

        $this->get(route('media.show', $media))->assertNotFound();
    }

    public function test_root_can_stream_private_product_pdf_inline_or_as_a_download(): void
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
        self::assertStringContainsString('inline', $response->headers->get('Content-Disposition', ''));
        self::assertSame('private-pdf-content', $response->streamedContent());

        $download = $this->withoutMiddleware(EnsureActiveAuthenticationSession::class)
            ->actingAs($root)
            ->get(route('media.private', $media).'?download=1');

        $download->assertOk();
        self::assertStringContainsString('attachment', $download->headers->get('Content-Disposition', ''));
        self::assertSame('private-pdf-content', $download->streamedContent());
    }

    public function test_upload_rejects_a_file_whose_real_mime_does_not_match_its_extension(): void
    {
        Storage::fake('local');
        Storage::fake('public');

        $cooperative = Cooperative::factory()->create();
        $product = Product::factory()->forCooperative($cooperative)->create();
        $actor = $this->userWithRole('super_admin_root', (string) $cooperative->getKey());

        $response = $this->withoutMiddleware(EnsureActiveAuthenticationSession::class)
            ->actingAs($actor)
            ->withHeader('Accept', 'application/json')
            ->call(
                'POST',
                route('media.store'),
                [
                    'owner_type' => 'product',
                    'owner_id' => (string) $product->getKey(),
                    'category' => 'product_image',
                ],
                [],
                [
                    'file' => UploadedFile::fake()->createWithContent(
                        'spoofed.jpg',
                        '%PDF-1.4\\n1 0 obj\\n<< /Type /Catalog >>\\nendobj\\n%%EOF',
                    ),
                ],
                [
                    'HTTP_ACCEPT' => 'application/json',
                    'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest',
                    'HTTP_SEC_FETCH_SITE' => 'same-origin',
                ],
            );

        self::assertSame(422, $response->status());
        $response->assertJsonValidationErrors('file');
        self::assertSame(0, Media::query()->count());
        self::assertSame([], Storage::disk('public')->allFiles('media'));
    }

    public function test_upload_rejects_a_category_that_does_not_match_the_owner_type(): void
    {
        Storage::fake('local');
        Storage::fake('public');

        $cooperative = Cooperative::factory()->create();
        $actor = $this->userWithRole('super_admin_root', (string) $cooperative->getKey());

        $response = $this->withoutMiddleware(EnsureActiveAuthenticationSession::class)
            ->actingAs($actor)
            ->withHeader('Accept', 'application/json')
            ->call(
                'POST',
                route('media.store'),
                [
                    'owner_type' => 'cooperative',
                    'owner_id' => (string) $cooperative->getKey(),
                    'category' => 'product_image',
                ],
                [],
                [
                    'file' => UploadedFile::fake()->createWithContent(
                        'not-an-image.pdf',
                        '%PDF-1.4\\n1 0 obj\\n<< /Type /Catalog >>\\nendobj\\n%%EOF',
                    ),
                ],
                [
                    'HTTP_ACCEPT' => 'application/json',
                    'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest',
                    'HTTP_SEC_FETCH_SITE' => 'same-origin',
                ],
            );

        self::assertSame(422, $response->status());
        $response->assertJsonValidationErrors('category');
        self::assertSame(0, Media::query()->count());
        self::assertSame([], Storage::disk('public')->allFiles('media'));
    }

    public function test_public_product_image_is_hidden_when_product_has_no_catalog(): void
    {
        Storage::fake('public');

        $cooperative = Cooperative::factory()->create(['status' => CooperativeStatus::ACTIVE]);
        $product = Product::factory()->forCooperative($cooperative)->create(['is_active' => true]);
        $path = 'media/no-catalog/original.jpg';
        Storage::disk('public')->put($path, 'image-content');
        $media = $product->media()->create($this->attributes($path, 'product_image', 'public', 'public', 'image/jpeg', 'jpg'));

        $this->get(route('media.show', $media))->assertNotFound();
    }

    public function test_public_product_image_is_hidden_when_its_only_catalog_is_inactive(): void
    {
        Storage::fake('public');

        $cooperative = Cooperative::factory()->create(['status' => CooperativeStatus::ACTIVE]);
        $product = Product::factory()->forCooperative($cooperative)->create(['is_active' => true]);
        $catalog = Catalog::factory()->create([
            'cooperative_id' => $cooperative->getKey(),
            'is_active' => false,
        ]);
        $product->catalogs()->attach($catalog->getKey());
        $path = 'media/inactive-catalog/original.jpg';
        Storage::disk('public')->put($path, 'image-content');
        $media = $product->media()->create($this->attributes($path, 'product_image', 'public', 'public', 'image/jpeg', 'jpg'));

        $this->get(route('media.show', $media))->assertNotFound();
    }

    public function test_public_product_image_is_streamed_when_product_belongs_to_an_active_catalog_of_its_cooperative(): void
    {
        Storage::fake('public');

        $cooperative = Cooperative::factory()->create(['status' => CooperativeStatus::ACTIVE]);
        $product = Product::factory()->forCooperative($cooperative)->create(['is_active' => true]);
        $catalog = Catalog::factory()->create([
            'cooperative_id' => $cooperative->getKey(),
            'is_active' => true,
        ]);
        $product->catalogs()->attach($catalog->getKey());
        $path = 'media/active-catalog/original.jpg';
        Storage::disk('public')->put($path, 'image-content');
        $media = $product->media()->create($this->attributes($path, 'product_image', 'public', 'public', 'image/jpeg', 'jpg'));

        $response = $this->get(route('media.show', $media));

        $response->assertOk();
        self::assertSame('image-content', $response->streamedContent());
    }

    public function test_public_product_image_is_hidden_when_its_catalog_is_soft_deleted(): void
    {
        Storage::fake('public');

        $cooperative = Cooperative::factory()->create(['status' => CooperativeStatus::ACTIVE]);
        $product = Product::factory()->forCooperative($cooperative)->create(['is_active' => true]);
        $catalog = Catalog::factory()->create([
            'cooperative_id' => $cooperative->getKey(),
            'is_active' => true,
        ]);
        $product->catalogs()->attach($catalog->getKey());
        $catalog->delete();
        $path = 'media/deleted-catalog/original.jpg';
        Storage::disk('public')->put($path, 'image-content');
        $media = $product->media()->create($this->attributes($path, 'product_image', 'public', 'public', 'image/jpeg', 'jpg'));

        $this->get(route('media.show', $media))->assertNotFound();
    }

    public function test_generic_upload_http_route_resolves_product_cooperative_and_own_user_owners(): void
    {
        Storage::fake('local');

        $cooperative = Cooperative::factory()->active()->create();
        $product = Product::factory()->forCooperative($cooperative)->create();
        $root = $this->userWithRole('super_admin_root', (string) $cooperative->getKey());
        $selfUser = $this->userWithRole('user', (string) $cooperative->getKey());

        $owners = [
            'product' => [$product, $root],
            'cooperative' => [$cooperative, $root],
            'user' => [$selfUser, $selfUser],
        ];

        foreach ($owners as $ownerType => [$owner, $actor]) {
            $this->withoutMiddleware(EnsureActiveAuthenticationSession::class)
                ->actingAs($actor)
                ->post(route('media.store'), [
                    'owner_type' => $ownerType,
                    'owner_id' => (string) $owner->getKey(),
                    'category' => 'document',
                    'document_category' => 'legal',
                    'file' => UploadedFile::fake()->createWithContent(
                        'proof.pdf',
                        '%PDF-1.4\\n1 0 obj\\n<< /Type /Catalog >>\\nendobj\\n%%EOF',
                    ),
                ])
                ->assertCreated()
                ->assertJsonPath('data.category', 'document')
                ->assertJsonPath('data.visibility', 'private');

            $media = $owner->media()
                ->where('category', 'document')
                ->firstOrFail();

            self::assertSame('legal', $media->metadata['document_category']);
        }
    }

    public function test_owner_history_http_route_resolves_media_for_all_linked_domains(): void
    {
        Notification::fake();

        $cooperative = Cooperative::factory()->create();
        $product = Product::factory()->forCooperative($cooperative)->create();
        $ownerUser = User::factory()->create(['cooperative_id' => $cooperative->getKey()]);
        $incidentReporter = $this->userWithRole('super_admin_root', (string) $cooperative->getKey());
        $incident = app(MediaIncidentService::class)->create(
            $incidentReporter,
            'Polymorphic history incident',
            'Exercise MEDIA owner history across domain boundaries.',
        );

        $root = $this->userWithRole('super_admin_root', (string) $cooperative->getKey());

        $owners = [
            'product' => $product,
            'cooperative' => $cooperative,
            'user' => $ownerUser,
            'incident' => $incident,
        ];

        foreach ($owners as $ownerType => $owner) {
            $category = $ownerType === 'incident' ? 'incident_attachment' : 'document';
            $attributes = $this->attributes(
                'media/inter-domain/'.$ownerType.'/'.$owner->getKey().'.pdf',
                $category,
                'private',
                'local',
                'application/pdf',
                'pdf',
            );

            if ($ownerType === 'incident') {
                $attributes['collection'] = 'incident-attachments';
            }

            $media = $owner->media()->create($attributes);

            $this->withoutMiddleware(EnsureActiveAuthenticationSession::class)
                ->actingAs($root)
                ->getJson(route('media.owners.history', [
                    'ownerType' => $ownerType,
                    'ownerId' => (string) $owner->getKey(),
                ]))
                ->assertOk()
                ->assertJsonCount(1, 'data')
                ->assertJsonPath('data.0.id', (string) $media->getKey());
        }
    }

    public function test_media_owner_observer_is_registered_for_product_cooperative_and_user_deletion(): void
    {
        $cooperative = Cooperative::factory()->create();
        $product = Product::factory()->forCooperative($cooperative)->create();
        $ownerUser = User::factory()->create(['cooperative_id' => $cooperative->getKey()]);

        foreach ([$product, $cooperative, $ownerUser] as $owner) {
            $owner->media()->create($this->attributes(
                'media/observer/'.$owner::class.'/'.$owner->getKey().'.pdf',
                'document',
                'private',
                'local',
                'application/pdf',
                'pdf',
            ));

            $owner->delete();

            self::assertDatabaseHas('audit_events', [
                'event_key' => 'media.owner_soft_deleted',
                'subject_type' => $owner::class,
                'subject_id' => $owner->getKey(),
            ]);
        }
    }

    public function test_restore_endpoint_rejects_make_primary_for_product_images(): void
    {
        self::assertTrue(function_exists('imagecreatetruecolor'), 'PHP GD must be enabled for Media V1.');
        Storage::fake('local');
        Storage::fake('public');
        config(['media.public_disk' => 'public']);

        $cooperative = Cooperative::factory()->create();
        $product = Product::factory()->forCooperative($cooperative)->create();
        $root = $this->userWithRole('super_admin_root', (string) $cooperative->getKey());

        $media = app(MediaService::class)->upload(
            $root,
            $product,
            UploadedFile::fake()->image('restore-primary.jpg', 600, 400),
            'product_image',
        );
        app(MediaService::class)->delete($root, $media);

        $this->withoutMiddleware(EnsureActiveAuthenticationSession::class)
            ->actingAs($root)
            ->postJson(route('media.restore', $media), ['make_primary' => true])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('make_primary');

        self::assertSoftDeleted('media', ['id' => $media->getKey()]);
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
