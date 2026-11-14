<?php

declare(strict_types=1);

namespace Tests\Framework\Domains\Media\Services;

use App\Domains\Media\Models\Media;
use App\Domains\Media\Services\MediaCleanupService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

final class MediaCleanupServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_purges_media_files_only_after_the_retention_period(): void
    {
        Storage::fake('local');
        config(['media.retention_days' => 30]);

        Storage::disk('local')->put('media/expired/original.pdf', '%PDF-1.4');
        Storage::disk('local')->put('media/expired/thumbnail.jpg', 'thumbnail');
        Storage::disk('local')->put('media/recent/original.pdf', '%PDF-1.4');

        $expired = Media::query()->create($this->attributes(
            'media/expired/original.pdf',
            [
                'derivatives' => ['thumbnail' => 'media/expired/thumbnail.jpg'],
            ],
            now()->subDays(31),
        ));

        $recent = Media::query()->create($this->attributes(
            'media/recent/original.pdf',
            [],
            now()->subDays(5),
        ));

        $purged = app(MediaCleanupService::class)->purgeExpired();

        self::assertSame(1, $purged);
        self::assertFalse(Media::withTrashed()->whereKey($expired->getKey())->exists());
        self::assertTrue(Media::withTrashed()->whereKey($recent->getKey())->exists());
        Storage::disk('local')->assertMissing('media/expired/original.pdf');
        Storage::disk('local')->assertMissing('media/expired/thumbnail.jpg');
        Storage::disk('local')->assertExists('media/recent/original.pdf');
    }

    /**
     * @param array<string, mixed> $metadata
     * @return array<string, mixed>
     */
    private function attributes(string $path, array $metadata, mixed $deletedAt): array
    {
        return [
            'mediable_type' => 'App\\Domains\\Products\\Models\\Product',
            'mediable_id' => '01JTESTPRODUCT00000000000000',
            'collection' => 'documents',
            'category' => 'document',
            'disk' => 'local',
            'path' => $path,
            'original_name' => 'document.pdf',
            'mime_type' => 'application/pdf',
            'extension' => 'pdf',
            'size' => 100,
            'visibility' => 'private',
            'is_primary' => false,
            'sort_order' => 0,
            'metadata' => $metadata,
            'deleted_at' => $deletedAt,
        ];
    }
}
