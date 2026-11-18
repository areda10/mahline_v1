<?php

declare(strict_types=1);

namespace App\Domains\Media\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

final class MediaOrphanCleanupService
{
    /**
     * Detect unreferenced files and delete only candidates older than the safety period.
     */
    public function detectAndPurge(): int
    {
        $configuredHours = filter_var(config('media.orphan_safety_hours', 24), FILTER_VALIDATE_INT);
        $safetyHours = $configuredHours !== false && $configuredHours >= 24
            ? $configuredHours
            : 24;
        $cutoff = now()->subHours($safetyHours);
        $deleted = 0;

        $disks = array_values(array_unique(array_filter([
            (string) config('media.public_disk', 'local'),
            (string) config('media.private_disk', 'local'),
        ])));

        foreach ($disks as $diskName) {
            try {
                $disk = Storage::disk($diskName);
                $referenced = $this->referencedPaths($diskName);
                $files = $disk->allFiles('media');

                foreach ($files as $path) {
                    if (! $this->isManagedPath($path)) {
                        continue;
                    }

                    if (isset($referenced[$path])) {
                        DB::table('media_orphan_candidates')
                            ->where('disk', $diskName)
                            ->where('path', $path)
                            ->delete();

                        continue;
                    }

                    $now = now();
                    $pathHash = hash('sha256', $path);
                    DB::table('media_orphan_candidates')->insertOrIgnore([
                        'disk' => $diskName,
                        'path' => $path,
                        'path_hash' => $pathHash,
                        'first_seen_at' => $now,
                        'last_seen_at' => $now,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                    DB::table('media_orphan_candidates')
                        ->where('disk', $diskName)
                        ->where('path_hash', $pathHash)
                        ->update(['last_seen_at' => $now, 'updated_at' => $now]);

                    $candidate = DB::table('media_orphan_candidates')
                        ->where('disk', $diskName)
                        ->where('path', $path)
                        ->first();

                    if ($candidate === null || $candidate->first_seen_at > $cutoff->toDateTimeString()) {
                        Log::notice('MEDIA orphan file detected; safety period has not elapsed.', [
                            'disk' => $diskName,
                            'path_hash' => hash('sha256', $path),
                        ]);

                        continue;
                    }

                    // Recheck the database immediately before deletion to avoid racing a completed upload.
                    if ($this->isReferenced($diskName, $path)) {
                        DB::table('media_orphan_candidates')
                            ->where('disk', $diskName)
                            ->where('path', $path)
                            ->delete();

                        continue;
                    }

                    if ($disk->exists($path) && ! $disk->delete($path)) {
                        Log::error('MEDIA orphan file deletion failed.', [
                            'disk' => $diskName,
                            'path_hash' => hash('sha256', $path),
                        ]);

                        continue;
                    }

                    DB::table('media_orphan_candidates')
                        ->where('disk', $diskName)
                        ->where('path', $path)
                        ->delete();
                    $deleted++;

                    Log::info('MEDIA orphan file deleted after safety period.', [
                        'disk' => $diskName,
                        'path_hash' => hash('sha256', $path),
                    ]);
                }
            } catch (\Throwable $exception) {
                Log::error('MEDIA orphan scan failed for a configured disk.', [
                    'disk' => $diskName,
                    'exception' => $exception::class,
                ]);
            }
        }

        return $deleted;
    }

    /**
     * @return array<string, true>
     */
    private function referencedPaths(string $disk): array
    {
        $references = [];

        foreach (DB::table('media')->where('disk', $disk)->get(['path', 'metadata']) as $media) {
            if (is_string($media->path)) {
                $references[$media->path] = true;
            }

            $metadata = is_string($media->metadata)
                ? json_decode($media->metadata, true)
                : null;
            $derivatives = is_array($metadata) ? ($metadata['derivatives'] ?? []) : [];

            if (is_array($derivatives)) {
                foreach ($derivatives as $path) {
                    if (is_string($path)) {
                        $references[$path] = true;
                    }
                }
            }
        }

        return $references;
    }

    private function isReferenced(string $disk, string $path): bool
    {
        foreach (DB::table('media')->where('disk', $disk)->get(['path', 'metadata']) as $media) {
            if ($media->path === $path) {
                return true;
            }

            $metadata = is_string($media->metadata)
                ? json_decode($media->metadata, true)
                : null;
            $derivatives = is_array($metadata) ? ($metadata['derivatives'] ?? []) : [];

            if (is_array($derivatives) && in_array($path, $derivatives, true)) {
                return true;
            }
        }

        return false;
    }

    private function isManagedPath(string $path): bool
    {
        if (! str_starts_with($path, 'media/')) {
            return false;
        }

        foreach (explode('/', $path) as $segment) {
            if ($segment === '' || $segment === '.' || $segment === '..') {
                return false;
            }
        }

        return true;
    }
}
