<?php

declare(strict_types=1);

namespace Tests\Framework\Domains\Media\Services;

use App\Domains\Media\Models\Media;
use App\Domains\Media\Services\MediaCleanupService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

final class MediaCleanupServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_purges_media_files_only_after_the_retention_period(): void
    {
        Storage::fake('public');
        config(['media.retention_days' => 30]);

        Storage::disk('public')->put('media/expired/original.jpg', 'original');
        Storage::disk('public')->put('media/expired/thumbnail.jpg', 'thumbnail');
        Storage::disk('public')->put('media/recent/original.jpg', 'original');

        $expired = Media::query()->create($this->attributes(
            'media/expired/original.jpg',
            [
                'derivatives' => ['thumbnail' => 'media/expired/thumbnail.jpg'],
            ],
            now()->subDays(31),
        ));

        $recent = Media::query()->create($this->attributes(
            'media/recent/original.jpg',
            [],
            now()->subDays(5),
        ));

        $purged = app(MediaCleanupService::class)->purgeExpired();

        self::assertSame(1, $purged);
        self::assertFalse(Media::withTrashed()->whereKey($expired->getKey())->exists());
        self::assertTrue(Media::withTrashed()->whereKey($recent->getKey())->exists());
        Storage::disk('public')->assertMissing('media/expired/original.jpg');
        Storage::disk('public')->assertMissing('media/expired/thumbnail.jpg');
        Storage::disk('public')->assertExists('media/recent/original.jpg');
    }

    /**
     * @param array<string, mixed> $metadata
     * @return array<string, mixed>
     */
    private function attributes(string $path, array $metadata, mixed $deletedAt): array
    {
        return [
            'mediable_type' => 'App\\Domains\\Products\\Models\\Product',
            'mediable_id' => (string) Str::ulid(),
            'collection' => 'product-gallery',
            'category' => 'product_image',
            'disk' => 'public',
            'path' => $path,
            'original_name' => 'product.jpg',
            'mime_type' => 'image/jpeg',
            'extension' => 'jpg',
            'size' => 100,
            'visibility' => 'public',
            'is_primary' => false,
            'sort_order' => 0,
            'metadata' => $metadata,
            'deleted_at' => $deletedAt,
        ];
    }
}
