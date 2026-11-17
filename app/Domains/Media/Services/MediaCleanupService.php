<?php

declare(strict_types=1);

namespace App\Domains\Media\Services;

use App\Domains\Media\Models\Media;
use Illuminate\Support\Facades\Storage;

final class MediaCleanupService
{
    public function purgeExpired(): int
    {
        $retentionDays = config('media.retention_days');

        if (! is_numeric($retentionDays) || (int) $retentionDays < 1) {
            return 0;
        }

        $cutoff = now()->subDays((int) $retentionDays);
        $purged = 0;

        Media::onlyTrashed()
            ->where('deleted_at', '<=', $cutoff)
            ->chunkById(100, function ($items) use (&$purged): void {
                foreach ($items as $media) {
                    $disk = Storage::disk($media->disk);
                    $paths = [$media->path];

                    if ($media->category !== 'document') {
                        foreach (['medium', 'thumbnail'] as $variant) {
                            $paths[] = dirname($media->path).'/'.$variant.'.'.$media->extension;
                        }
                    }

                    if (! $disk->delete($paths)) {
                        continue;
                    }

                    $media->forceDelete();
                    $purged++;
                }
            });

        return $purged;
    }
}
