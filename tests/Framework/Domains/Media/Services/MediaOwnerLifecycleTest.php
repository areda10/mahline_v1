<?php

declare(strict_types=1);

namespace Tests\Framework\Domains\Media\Services;

use App\Domains\Cooperatives\Models\Cooperative;
use App\Domains\Identity\Authorization\Models\Role;
use App\Domains\Identity\Users\Models\User;
use App\Domains\Media\Services\MediaService;
use App\Domains\Products\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

final class MediaOwnerLifecycleTest extends TestCase
{
    use RefreshDatabase;

    public function test_force_deleting_an_owner_soft_deletes_its_active_media_and_retains_files(): void
    {
        Storage::fake('local');
        $actor = $this->rootUser();
        $product = Product::factory()->create();
        $media = app(MediaService::class)->upload(
            $actor,
            $product,
            UploadedFile::fake()->createWithContent(
                'owner-document.pdf',
                "%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\n%%EOF",
            ),
            'document',
            'legal',
        );

        $product->forceDelete();

        self::assertSoftDeleted('media', ['id' => $media->getKey()]);
        Storage::disk('local')->assertExists($media->path);
        self::assertDatabaseHas('audit_events', [
            'event_key' => 'media.owner_force_deleted',
            'subject_id' => $media->getKey(),
        ]);
    }

    public function test_owner_restore_keeps_valid_media_and_soft_deletes_corrupt_media(): void
    {
        Storage::fake('local');
        $actor = $this->rootUser();
        $product = Product::factory()->create();

        $valid = app(MediaService::class)->upload(
            $actor,
            $product,
            UploadedFile::fake()->createWithContent(
                'valid-owner.pdf',
                "%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\n%%EOF",
            ),
            'document',
            'legal',
        );
        $corrupt = app(MediaService::class)->upload(
            $actor,
            $product,
            UploadedFile::fake()->createWithContent(
                'corrupt-owner.pdf',
                "%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\n%%EOF",
            ),
            'document',
            'certification',
        );

        $product->delete();
        self::assertDatabaseHas('audit_events', [
            'event_key' => 'media.owner_soft_deleted',
            'subject_id' => $product->getKey(),
        ]);
        Storage::disk('local')->put($corrupt->path, 'not-a-pdf');
        $this->actingAs($actor);
        $product->restore();

        self::assertDatabaseHas('media', ['id' => $valid->getKey(), 'deleted_at' => null]);
        self::assertSoftDeleted('media', ['id' => $corrupt->getKey()]);
        self::assertDatabaseHas('audit_events', [
            'event_key' => 'media.owner_restore_skipped_invalid',
            'subject_id' => $corrupt->getKey(),
        ]);
    }

    public function test_soft_deleting_a_cooperative_soft_deletes_its_private_documents_and_starts_retention(): void
    {
        Storage::fake('local');
        $actor = $this->rootUser();
        $cooperative = Cooperative::factory()->create();

        $privateDocument = app(MediaService::class)->upload(
            $actor,
            $cooperative,
            UploadedFile::fake()->createWithContent(
                'cooperative-private.pdf',
                "%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\n%%EOF",
            ),
            'document',
            'legal',
        );

        $this->actingAs($actor);
        $cooperative->delete();

        $deletedDocument = $privateDocument->fresh();
        self::assertNotNull($deletedDocument->deleted_at);
        self::assertSame($cooperative->deleted_at?->toDateTimeString(), $deletedDocument->deleted_at?->toDateTimeString());
        Storage::disk('local')->assertExists($privateDocument->path);
        self::assertDatabaseHas('audit_events', [
            'event_key' => 'media.cooperative_private_media_soft_deleted',
            'subject_id' => $privateDocument->getKey(),
            'cooperative_id' => $cooperative->getKey(),
        ]);
    }

    public function test_restoring_a_cooperative_restores_valid_private_documents_but_keeps_corrupt_ones_deleted(): void
    {
        Storage::fake('local');
        $actor = $this->rootUser();
        $cooperative = Cooperative::factory()->create();

        $valid = app(MediaService::class)->upload(
            $actor,
            $cooperative,
            UploadedFile::fake()->createWithContent(
                'valid-cooperative.pdf',
                "%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\n%%EOF",
            ),
            'document',
            'legal',
        );
        $corrupt = app(MediaService::class)->upload(
            $actor,
            $cooperative,
            UploadedFile::fake()->createWithContent(
                'corrupt-cooperative.pdf',
                "%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\n%%EOF",
            ),
            'document',
            'certification',
        );

        $this->actingAs($actor);
        $cooperative->delete();
        Storage::disk('local')->put($corrupt->path, 'tampered-pdf');

        $cooperative->restore();

        self::assertDatabaseHas('media', ['id' => $valid->getKey(), 'deleted_at' => null]);
        self::assertSoftDeleted('media', ['id' => $corrupt->getKey()]);
        self::assertDatabaseHas('audit_events', [
            'event_key' => 'media.restored',
            'subject_id' => $valid->getKey(),
        ]);
        self::assertDatabaseHas('audit_events', [
            'event_key' => 'media.restore_rejected',
            'subject_id' => $corrupt->getKey(),
        ]);
        self::assertDatabaseHas('audit_events', [
            'event_key' => 'media.cooperative_private_media_restore_processed',
            'subject_id' => $cooperative->getKey(),
        ]);
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
