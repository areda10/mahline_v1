<?php

declare(strict_types=1);

namespace Tests\Framework\Domains\Media\Services;

use App\Domains\Audit\Models\AuditEvent;
use App\Domains\Identity\Authorization\Models\Role;
use App\Domains\Identity\Users\Models\User;
use App\Domains\Media\Models\Media;
use App\Domains\Media\Services\MediaService;
use App\Domains\Products\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
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
            'proof_of_origin',
        );

        self::assertInstanceOf(Media::class, $media);
        self::assertSame('private', $media->visibility);
        self::assertSame('local', $media->disk);
        self::assertSame('proof_of_origin', $media->metadata['document_category']);
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
            'proof_of_origin',
        );

        app(MediaService::class)->delete($actor, $media);

        self::assertSoftDeleted('media', ['id' => $media->getKey()]);
        Storage::disk('local')->assertExists($media->path);
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
