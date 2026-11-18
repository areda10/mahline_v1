<?php

declare(strict_types=1);

namespace App\Domains\Media\Services;

use App\Domains\Audit\Repositories\AuditEventRepository;
use App\Domains\Audit\Services\AuditService;
use App\Domains\Media\Models\Media;
use App\Shared\DTOs\AuditEntryDTO;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

final class MediaCleanupService
{
    public function __construct(
        private readonly AuditService $audit,
        private readonly AuditEventRepository $auditEvents,
    ) {}

    public function purgeExpired(): int
    {
        $retentionDays = filter_var(config('media.retention_days', 90), FILTER_VALIDATE_INT);

        if ($retentionDays === false || $retentionDays < 1) {
            Log::warning('MEDIA purge skipped because retention configuration is invalid.');

            return 0;
        }

        $cutoff = now()->subDays($retentionDays);
        $purged = 0;

        Media::onlyTrashed()
            ->where('deleted_at', '<=', $cutoff)
            ->orderBy('id')
            ->chunkById(100, function ($items) use (&$purged): void {
                foreach ($items as $item) {
                    try {
                        $wasPurged = DB::transaction(function () use ($item): bool {
                            $media = Media::onlyTrashed()
                                ->whereKey($item->getKey())
                                ->lockForUpdate()
                                ->first();

                            if (! $media instanceof Media) {
                                return false;
                            }

                            if (! $this->auditEvents->verifyIntegrity()) {
                                throw new \RuntimeException('La chaîne d’audit est invalide ; purge MEDIA refusée.');
                            }

                            $disk = Storage::disk($media->disk);
                            $remaining = [];

                            foreach ($this->managedPaths($media) as $path) {
                                if (! $this->isManagedPath($path)) {
                                    $remaining[] = $path;
                                    Log::error('MEDIA purge refused an unmanaged storage path.', [
                                        'media_id' => (string) $media->getKey(),
                                    ]);

                                    continue;
                                }

                                try {
                                    if ($disk->exists($path) && ! $disk->delete($path)) {
                                        $remaining[] = $path;
                                        Log::error('MEDIA purge could not delete a retained file; purge will resume.', [
                                            'media_id' => (string) $media->getKey(),
                                        ]);
                                    }
                                } catch (\Throwable $exception) {
                                    $remaining[] = $path;
                                    Log::error('MEDIA file deletion failed; purge will resume on the next run.', [
                                        'media_id' => (string) $media->getKey(),
                                        'exception' => $exception::class,
                                    ]);
                                }
                            }

                            if ($remaining !== []) {
                                $metadata = $media->metadata ?? [];
                                $metadata['purge_remaining_paths'] = array_values(array_unique($remaining));
                                $media->forceFill(['metadata' => $metadata])->save();

                                return false;
                            }

                            $this->audit->record(new AuditEntryDTO(
                                eventKey: 'media.purged',
                                action: 'purged',
                                subjectType: Media::class,
                                subjectId: (string) $media->getKey(),
                                metadata: [
                                    'category' => $media->category,
                                    'retention_days' => (int) config('media.retention_days', 90),
                                ],
                            ));

                            $media->forceDelete();

                            return true;
                        }, 3);

                        if ($wasPurged) {
                            $purged++;
                            Log::info('MEDIA record and files purged.', [
                                'media_id' => (string) $item->getKey(),
                            ]);
                        }
                    } catch (\Throwable $exception) {
                        Log::error('MEDIA database purge failed; purge will resume on the next run.', [
                            'media_id' => (string) $item->getKey(),
                            'exception' => $exception::class,
                        ]);
                    }
                }
            });

        return $purged;
    }

    /**
     * @return list<string>
     */
    private function managedPaths(Media $media): array
    {
        $paths = [$media->path];
        $derivatives = $media->metadata['derivatives'] ?? [];

        if (is_array($derivatives)) {
            foreach ($derivatives as $path) {
                if (is_string($path)) {
                    $paths[] = $path;
                }
            }
        }

        if ($media->category !== 'document' && $media->category !== 'incident_attachment' && $derivatives === []) {
            foreach (['thumbnail', 'small', 'medium', 'large'] as $variant) {
                $paths[] = dirname($media->path).'/'.$variant.'.'.$media->extension;
            }
        }

        return array_values(array_unique($paths));
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
