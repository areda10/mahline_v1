<?php

declare(strict_types=1);

namespace Tests\Framework\Domains\Media\Services;

use App\Domains\Audit\Models\AuditEvent;
use App\Domains\Cooperatives\Models\Cooperative;
use App\Domains\Identity\Authorization\Models\Role;
use App\Domains\Identity\Users\Models\User;
use App\Domains\Media\Models\Media;
use App\Domains\Media\Services\MediaService;
use App\Domains\Products\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

final class MediaServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_uploads_a_private_pdf_document_and_records_safe_audit_metadata(): void
    {
        Storage::fake('local');
        Storage::fake('public');

        $actor = $this->rootUser();
        $product = Product::factory()->create();
        $file = UploadedFile::fake()->createWithContent(
            'proof.pdf',
            "%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\n%%EOF",
        );

        $media = app(MediaService::class)->upload(
            $actor,
            $product,
            $file,
            'document',
            'legal',
        );

        self::assertInstanceOf(Media::class, $media);
        self::assertSame('private', $media->visibility);
        self::assertSame('local', $media->disk);
        self::assertSame('legal', $media->metadata['document_category']);
        self::assertFalse($media->is_primary);
        Storage::disk('local')->assertExists($media->path);
        self::assertDatabaseHas('audit_events', [
            'event_key' => 'media.uploaded',
            'subject_type' => Media::class,
            'subject_id' => $media->getKey(),
        ]);

        $event = AuditEvent::query()
            ->where('subject_id', $media->getKey())
            ->firstOrFail();

        self::assertArrayNotHasKey('path', $event->metadata ?? []);
        self::assertArrayNotHasKey('disk', $event->metadata ?? []);
    }

    public function test_it_removes_stored_files_when_media_database_persistence_fails(): void
    {
        Storage::fake('local');
        Storage::fake('public');

        $actor = $this->rootUser();
        $product = Product::factory()->create();

        Media::creating(static function (): void {
            throw new \RuntimeException('Simulated media persistence failure.');
        });

        try {
            app(MediaService::class)->upload(
                $actor,
                $product,
                UploadedFile::fake()->createWithContent(
                    'rollback-proof.pdf',
                    "%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\n%%EOF",
                ),
                'document',
                'legal',
            );

            self::fail('The simulated persistence failure should be rethrown.');
        } catch (\RuntimeException $exception) {
            self::assertSame('Simulated media persistence failure.', $exception->getMessage());
        }

        self::assertSame(0, Media::query()->count());
        self::assertSame([], Storage::disk('local')->allFiles('media'));
    }

    public function test_it_removes_a_partially_written_file_when_storage_reports_failure(): void
    {
        Storage::fake('local');
        Storage::fake('public');

        $disk = Storage::disk('local');
        $failingDisk = \Mockery::mock($disk)->makePartial();
        $failingDisk->shouldReceive('put')
            ->once()
            ->andReturnUsing(static function (string $path, string $contents) use ($disk): bool {
                $disk->put($path, $contents);

                return false;
            });

        Storage::set('local', $failingDisk);

        $actor = $this->rootUser();
        $product = Product::factory()->create();

        try {
            app(MediaService::class)->upload(
                $actor,
                $product,
                UploadedFile::fake()->createWithContent(
                    'partial-write.pdf',
                    "%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\n%%EOF",
                ),
                'document',
                'legal',
            );

            self::fail('The simulated storage failure should be rethrown.');
        } catch (\RuntimeException $exception) {
            self::assertSame('Le stockage du média a échoué.', $exception->getMessage());
        }

        self::assertSame([], $disk->allFiles('media'));
        self::assertSame(0, Media::query()->count());
    }

    public function test_it_removes_a_partially_written_image_derivative_when_storage_reports_failure(): void
    {
        Storage::fake('local');
        Storage::fake('public');
        config(['media.public_disk' => 'public']);

        $disk = Storage::disk('public');
        $writeCount = 0;
        $failingDisk = \Mockery::mock($disk)->makePartial();
        $failingDisk->shouldReceive('put')
            ->twice()
            ->andReturnUsing(static function (string $path, string $contents) use ($disk, &$writeCount): bool {
                $writeCount++;
                $disk->put($path, $contents);

                return $writeCount !== 2;
            });

        Storage::shouldReceive('disk')
            ->with('public')
            ->andReturn($failingDisk);

        $actor = $this->rootUser();
        $product = Product::factory()->create();

        try {
            app(MediaService::class)->upload(
                $actor,
                $product,
                UploadedFile::fake()->image('partial-derivative.jpg', 160, 120),
                'product_image',
            );

            self::fail('The simulated derivative storage failure should be rethrown.');
        } catch (\RuntimeException $exception) {
            self::assertSame('Le stockage du média a échoué.', $exception->getMessage());
        }

        self::assertSame(2, $writeCount);
        self::assertSame([], $disk->allFiles('media'));
        self::assertSame(0, Media::query()->count());
    }

    public function test_soft_deleting_a_media_record_keeps_its_file_for_the_retention_period(): void
    {
        Storage::fake('local');
        Storage::fake('public');

        $actor = $this->rootUser();
        $product = Product::factory()->create();
        $file = UploadedFile::fake()->createWithContent(
            'proof.pdf',
            "%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\n%%EOF",
        );

        $media = app(MediaService::class)->upload(
            $actor,
            $product,
            $file,
            'document',
            'legal',
        );

        app(MediaService::class)->delete($actor, $media);

        self::assertSoftDeleted('media', ['id' => $media->getKey()]);
        Storage::disk('local')->assertExists($media->path);
    }

    public function test_it_stores_public_product_images_with_derivatives_and_supports_primary_selection(): void
    {
        self::assertTrue(function_exists('imagecreatetruecolor'), 'PHP GD must be enabled for Media V1.');

        Storage::fake('local');
        Storage::fake('public');
        config(['media.public_disk' => 'public']);

        $actor = $this->rootUser();
        $product = Product::factory()->create();
        $file = UploadedFile::fake()->image('product.jpg', 1600, 900);

        $media = app(MediaService::class)->upload(
            $actor,
            $product,
            $file,
            'product_image',
        );

        self::assertSame('public', $media->visibility);
        self::assertSame('public', $media->disk);
        self::assertTrue($media->is_primary);
        Storage::disk('public')->assertExists($media->path);
        foreach (['thumbnail', 'small', 'medium', 'large'] as $variant) {
            Storage::disk('public')->assertExists($media->metadata['derivatives'][$variant]);
            self::assertSame('image/webp', $media->metadata['derivative_mime_types'][$variant]);
        }

        $second = app(MediaService::class)->upload(
            $actor,
            $product,
            UploadedFile::fake()->image('product-second.jpg', 800, 600),
            'product_image',
        );

        self::assertFalse($second->is_primary);

        app(MediaService::class)->setPrimary($actor, $second);

        self::assertFalse($media->fresh()->is_primary);
        self::assertTrue($second->fresh()->is_primary);
        self::assertSame($second->getKey(), $product->fresh()->primaryImage->getKey());

        app(MediaService::class)->delete($actor, $second);

        self::assertSoftDeleted('media', ['id' => $second->getKey()]);
        self::assertTrue($media->fresh()->is_primary);
        self::assertSame($media->getKey(), $product->fresh()->primaryImage->getKey());
    }

    public function test_replacing_a_profile_image_soft_deletes_the_previous_file(): void
    {
        self::assertTrue(function_exists('imagecreatetruecolor'), 'PHP GD must be enabled for Media V1.');

        Storage::fake('local');
        Storage::fake('public');

        $actor = $this->rootUser();
        $service = app(MediaService::class);

        $first = $service->upload(
            $actor,
            $actor,
            UploadedFile::fake()->image('profile-one.jpg', 300, 300),
            'profile_image',
        );

        // Simulate a legacy/inconsistent active row that is not marked primary.
        $first->forceFill(['is_primary' => false])->save();

        $second = $service->upload(
            $actor,
            $actor,
            UploadedFile::fake()->image('profile-two.jpg', 300, 300),
            'profile_image',
        );

        self::assertSoftDeleted('media', ['id' => $first->getKey()]);
        self::assertSame($second->getKey(), $actor->fresh()->profileImage->getKey());
        Storage::disk('local')->assertExists($first->path);
        Storage::disk('local')->assertExists($second->path);
    }

    public function test_replacing_a_cooperative_logo_soft_deletes_every_active_logo_even_if_not_primary(): void
    {
        self::assertTrue(function_exists('imagecreatetruecolor'), 'PHP GD must be enabled for Media V1.');

        Storage::fake('local');
        Storage::fake('public');
        config(['media.public_disk' => 'public']);

        $actor = $this->rootUser();
        $cooperative = Cooperative::factory()->create();
        $service = app(MediaService::class);

        $first = $service->upload(
            $actor,
            $cooperative,
            UploadedFile::fake()->image('logo-one.jpg', 300, 300),
            'cooperative_logo',
        );

        $first->forceFill(['is_primary' => false])->save();

        $second = $service->upload(
            $actor,
            $cooperative,
            UploadedFile::fake()->image('logo-two.jpg', 300, 300),
            'cooperative_logo',
        );

        self::assertSoftDeleted('media', ['id' => $first->getKey()]);
        self::assertSame($second->getKey(), $cooperative->fresh()->logo->getKey());
        Storage::disk('public')->assertExists($first->path);
        Storage::disk('public')->assertExists($second->path);
    }

    public function test_it_can_reorder_media_within_an_entity_collection(): void
    {
        Storage::fake('local');

        $actor = $this->rootUser();
        $product = Product::factory()->create();
        $service = app(MediaService::class);

        $first = $service->upload(
            $actor,
            $product,
            UploadedFile::fake()->createWithContent('first.pdf', "%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\n%%EOF"),
            'document',
            'legal',
        );

        $second = $service->upload(
            $actor,
            $product,
            UploadedFile::fake()->createWithContent('second.pdf', "%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\n%%EOF"),
            'document',
            'certification',
        );

        self::assertSame(1, $first->sort_order);
        self::assertSame(2, $second->sort_order);

        $service->reorder($actor, $second, 0);

        self::assertSame(0, $second->fresh()->sort_order);
    }

    public function test_product_gallery_quota_is_enforced_inside_the_owner_transaction(): void
    {
        self::assertTrue(function_exists('imagecreatetruecolor'), 'PHP GD must be enabled for Media V1.');
        Storage::fake('local');
        Storage::fake('public');
        config(['media.public_disk' => 'public', 'media.limits.product_gallery' => 1]);

        $actor = $this->rootUser();
        $product = Product::factory()->create();
        $service = app(MediaService::class);

        $first = $service->upload(
            $actor,
            $product,
            UploadedFile::fake()->image('first.jpg', 120, 80),
            'product_image',
        );

        try {
            $service->upload(
                $actor,
                $product,
                UploadedFile::fake()->image('second.jpg', 120, 80),
                'product_image',
            );
            self::fail('The product gallery quota should reject a second active image.');
        } catch (ValidationException) {
            self::assertSame(1, $product->media()->where('category', 'product_image')->count());
        }

        self::assertSame(5, count(Storage::disk('public')->allFiles('media')));
        self::assertTrue(Storage::disk('public')->exists($first->path));
    }

    public function test_private_document_quota_is_enforced_per_owner(): void
    {
        Storage::fake('local');
        config(['media.limits.documents_per_owner' => 1]);

        $actor = $this->rootUser();
        $product = Product::factory()->create();
        $service = app(MediaService::class);
        $first = UploadedFile::fake()->createWithContent(
            'first.pdf',
            "%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\n%%EOF",
        );

        $service->upload($actor, $product, $first, 'document', 'legal');

        try {
            $service->upload(
                $actor,
                $product,
                UploadedFile::fake()->createWithContent(
                    'second.pdf',
                    "%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\n%%EOF",
                ),
                'document',
                'certification',
            );
            self::fail('The document quota should reject a second active document.');
        } catch (ValidationException) {
            self::assertSame(1, $product->media()->where('category', 'document')->count());
        }

        self::assertSame(1, count(Storage::disk('local')->allFiles('media')));
    }

    public function test_private_document_rejects_custom_document_categories(): void
    {
        Storage::fake('local');

        $actor = $this->rootUser();
        $product = Product::factory()->create();

        $this->expectException(ValidationException::class);

        app(MediaService::class)->upload(
            $actor,
            $product,
            UploadedFile::fake()->createWithContent(
                'proof.pdf',
                "%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\n%%EOF",
            ),
            'document',
            'proof_of_origin',
        );
    }

    public function test_root_can_restore_a_retained_document_when_every_file_is_valid(): void
    {
        Storage::fake('local');
        $actor = $this->rootUser();
        $product = Product::factory()->create();
        $media = app(MediaService::class)->upload(
            $actor,
            $product,
            UploadedFile::fake()->createWithContent(
                'restore-proof.pdf',
                "%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\n%%EOF",
            ),
            'document',
            'legal',
        );

        app(MediaService::class)->delete($actor, $media);
        $restored = app(MediaService::class)->restore($actor, $media->fresh(), false);

        self::assertFalse($restored->trashed());
        self::assertDatabaseHas('media', ['id' => $media->getKey(), 'deleted_at' => null]);
        Storage::disk('local')->assertExists($media->path);
        self::assertDatabaseHas('audit_events', [
            'event_key' => 'media.restored',
            'subject_id' => $media->getKey(),
        ]);
    }

    public function test_restore_is_refused_when_a_retained_file_is_missing(): void
    {
        Storage::fake('local');
        $actor = $this->rootUser();
        $product = Product::factory()->create();
        $media = app(MediaService::class)->upload(
            $actor,
            $product,
            UploadedFile::fake()->createWithContent(
                'missing-proof.pdf',
                "%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\n%%EOF",
            ),
            'document',
            'legal',
        );

        app(MediaService::class)->delete($actor, $media);
        Storage::disk('local')->delete($media->path);

        try {
            app(MediaService::class)->restore($actor, $media->fresh(), false);
            self::fail('A missing retained file must prevent restoration.');
        } catch (ValidationException) {
            self::assertSoftDeleted('media', ['id' => $media->getKey()]);
        }
    }

    public function test_restore_is_refused_when_a_retained_file_has_been_altered(): void
    {
        Storage::fake('local');
        $actor = $this->rootUser();
        $product = Product::factory()->create();
        $media = app(MediaService::class)->upload(
            $actor,
            $product,
            UploadedFile::fake()->createWithContent(
                'altered-proof.pdf',
                "%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\n%%EOF",
            ),
            'document',
            'legal',
        );

        app(MediaService::class)->delete($actor, $media);
        Storage::disk('local')->put($media->path, "%PDF-1.4\nALTERED CONTENT\n%%EOF");

        try {
            app(MediaService::class)->restore($actor, $media->fresh(), false);
            self::fail('An altered retained file must prevent restoration.');
        } catch (ValidationException) {
            self::assertSoftDeleted('media', ['id' => $media->getKey()]);
        }
    }

    public function test_replacement_history_is_linked_to_the_replacing_media_and_can_be_restored(): void
    {
        self::assertTrue(function_exists('imagecreatetruecolor'), 'PHP GD must be enabled for Media V1.');
        Storage::fake('local');
        $actor = $this->rootUser();
        $service = app(MediaService::class);

        $previous = $service->upload(
            $actor,
            $actor,
            UploadedFile::fake()->image('profile-old.jpg', 300, 300),
            'profile_image',
        );
        $current = $service->upload(
            $actor,
            $actor,
            UploadedFile::fake()->image('profile-new.jpg', 300, 300),
            'profile_image',
        );

        $history = $service->replacementHistory($actor, $current);
        self::assertTrue($history->contains(fn (Media $item): bool => $item->is($previous)));
        self::assertTrue($previous->fresh()->trashed());

        $restored = $service->restore($actor, $previous->fresh(), false);
        self::assertFalse($restored->trashed());
        self::assertSame($previous->getKey(), $actor->fresh()->profileImage->getKey());
        self::assertSoftDeleted('media', ['id' => $current->getKey()]);
    }

    private function rootUser(): User
    {
        $user = User::factory()->create();
        $role = Role::query()->create([
            'name' => 'Super Admin Root',
            'slug' => 'super_admin_root',
            'is_active' => true,
        ]);

        $user->roles()->attach($role->getKey());

        return $user->refresh();
    }
}
