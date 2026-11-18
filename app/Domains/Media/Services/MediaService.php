<?php

declare(strict_types=1);

namespace App\Domains\Media\Services;

use App\Domains\Audit\Repositories\AuditEventRepository;
use App\Domains\Audit\Services\AuditService;
use App\Domains\Cooperatives\Models\Cooperative;
use App\Domains\Identity\Users\Models\User;
use App\Domains\Media\Enums\MediaCategory;
use App\Domains\Media\Enums\MediaDocumentCategory;
use App\Domains\Media\Models\Media;
use App\Domains\Media\Notifications\MediaSecurityAlertNotification;
use App\Domains\Products\Models\Product;
use App\Shared\DTOs\AuditEntryDTO;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;

final class MediaService
{
    public function __construct(
        private readonly MediaValidationService $validator,
        private readonly MediaImageProcessor $processor,
        private readonly AuditService $audit,
        private readonly AuditEventRepository $auditEvents,
        private readonly MediaAntivirusScanner $antivirus,
    ) {}

    public function upload(
        User $actor,
        Model $owner,
        UploadedFile $file,
        string $category,
        ?string $documentCategory = null,
    ): Media {
        $mediaCategory = MediaCategory::tryFrom($category);

        if ($mediaCategory === null || ! $mediaCategory->allowsOwner($owner::class)) {
            throw ValidationException::withMessages([
                'category' => ['Cette catégorie de média ne peut pas être associée à cette entité.'],
            ]);
        }

        $pendingMedia = new Media(['category' => $category]);
        $pendingMedia->setRelation('mediable', $owner);
        $this->authorize($actor, 'upload', [$pendingMedia, $owner, $category]);

        if ($mediaCategory === MediaCategory::DOCUMENT) {
            $documentCategoryEnum = MediaDocumentCategory::tryFrom(trim((string) $documentCategory));

            if ($documentCategoryEnum === null) {
                throw ValidationException::withMessages([
                    'document_category' => ['La catégorie du document doit être identity, legal, administrative, certification ou other.'],
                ]);
            }

            $documentCategory = $documentCategoryEnum->value;
        } else {
            $documentCategory = null;
        }

        $validated = $this->validator->validate($file, $mediaCategory);

        if ($mediaCategory === MediaCategory::INCIDENT_ATTACHMENT) {
            $this->antivirus->scan($file);
        }

        $mediaId = (string) Str::ulid();
        $visibility = $mediaCategory->isPublic() ? 'public' : 'private';
        $disk = (string) config(
            $visibility === 'public' ? 'media.public_disk' : 'media.private_disk',
        );
        $directory = 'media/'.$mediaCategory->value.'/'.$owner->getKey().'/'.$mediaId;
        $path = $directory.'/original.'.$validated['extension'];
        $storedPaths = [];
        $originalChecksum = null;
        $derivativeChecksums = [];

        $derivatives = $mediaCategory->isImage()
            ? $this->processor->derivatives($file, $validated['extension'])
            : [];

        try {
            $sourcePath = $file->getRealPath();
            $originalContents = is_string($sourcePath)
                ? file_get_contents($sourcePath)
                : false;

            // Track the target before writing: a failed adapter may have partially written it.
            $storedPaths[] = $path;
            $this->putOrFail($disk, $path, $originalContents);
            $originalChecksum = hash('sha256', $originalContents);

            $derivativePaths = [];
            $derivativeMimeTypes = [];

            foreach ($derivatives as $name => $derivative) {
                $derivativePath = $directory.'/'.$name.'.'.$derivative['extension'];
                $storedPaths[] = $derivativePath;
                $this->putOrFail($disk, $derivativePath, $derivative['contents']);
                $derivativePaths[$name] = $derivativePath;
                $derivativeMimeTypes[$name] = $derivative['mime_type'];
                $derivativeChecksums[$name] = hash('sha256', $derivative['contents']);
            }

            return DB::transaction(function () use (
                $actor,
                $owner,
                $mediaCategory,
                $validated,
                $documentCategory,
                $visibility,
                $disk,
                $path,
                $derivativePaths,
                $derivativeMimeTypes,
                $originalChecksum,
                $derivativeChecksums,
                $mediaId,
            ): Media {
                $lockedOwner = $owner->newQuery()
                    ->lockForUpdate()
                    ->findOrFail($owner->getKey());

                if ($mediaCategory === MediaCategory::PRODUCT_IMAGE) {
                    $limit = $this->positiveLimit('media.limits.product_gallery', 10);
                    $activeCount = $lockedOwner->media()
                        ->where('category', $mediaCategory->value)
                        ->count();

                    if ($activeCount >= $limit) {
                        throw ValidationException::withMessages([
                            'file' => ["La galerie du produit est limitée à {$limit} images actives."],
                        ]);
                    }
                }

                if ($mediaCategory === MediaCategory::DOCUMENT) {
                    $limit = $this->positiveLimit('media.limits.documents_per_owner', 20);
                    $activeCount = $lockedOwner->media()
                        ->where('category', MediaCategory::DOCUMENT->value)
                        ->count();

                    if ($activeCount >= $limit) {
                        throw ValidationException::withMessages([
                            'file' => ["Le propriétaire est limité à {$limit} documents actifs."],
                        ]);
                    }
                }

                $isPrimary = $mediaCategory->isSingle();

                if ($mediaCategory === MediaCategory::PRODUCT_IMAGE) {
                    $isPrimary = ! $lockedOwner->media()
                        ->where('category', $mediaCategory->value)
                        ->where('is_primary', true)
                        ->exists();
                }

                if ($mediaCategory->isSingle()) {
                    $lockedOwner->media()
                        ->where('category', $mediaCategory->value)
                        ->get()
                        ->each(function (Media $previous) use ($actor, $mediaId): void {
                            $metadata = $previous->metadata ?? [];
                            $metadata['replacement_media_id'] = $mediaId;
                            $metadata['replacement_recorded_at'] = now()->toIso8601String();
                            $previous->forceFill([
                                'metadata' => $metadata,
                                'deleted_by' => (string) $actor->getKey(),
                            ])->save();
                            $previous->delete();
                            $this->record($actor, $previous, 'media.replaced', 'replaced');
                        });
                }

                $media = $lockedOwner->media()->create([
                    'id' => $mediaId,
                    'collection' => (string) config(
                        'media.collections.'.$mediaCategory->value,
                        'default',
                    ),
                    'category' => $mediaCategory->value,
                    'disk' => $disk,
                    'path' => $path,
                    'original_name' => $validated['original_name'],
                    'mime_type' => $validated['mime_type'],
                    'extension' => $validated['extension'],
                    'size' => $validated['size'],
                    'visibility' => $visibility,
                    'is_primary' => $isPrimary,
                    'sort_order' => (int) $lockedOwner->media()
                        ->where('category', $mediaCategory->value)
                        ->max('sort_order') + 1,
                    'metadata' => array_filter([
                        'derivatives' => $derivativePaths,
                        'derivative_mime_types' => $derivativeMimeTypes,
                        'checksums' => [
                            'original' => $originalChecksum,
                            'derivatives' => $derivativeChecksums,
                        ],
                        'document_category' => $documentCategory,
                    ], static fn (mixed $value): bool => $value !== null),
                    'created_by' => (string) $actor->getKey(),
                ]);

                $this->record($actor, $media, 'media.uploaded', 'uploaded');

                return $media;
            });
        } catch (\Throwable $exception) {
            foreach ($storedPaths as $storedPath) {
                try {
                    if (! Storage::disk($disk)->delete($storedPath)) {
                        Log::error('MEDIA upload rollback could not remove a created file.', [
                            'media_id' => $mediaId,
                            'category' => $mediaCategory->value,
                        ]);
                    }
                } catch (\Throwable $cleanupException) {
                    Log::error('MEDIA upload rollback cleanup failed.', [
                        'media_id' => $mediaId,
                        'category' => $mediaCategory->value,
                        'exception' => $cleanupException::class,
                    ]);
                }
            }

            throw $exception;
        }
    }

    public function setPrimary(User $actor, Media $media): Media
    {
        $this->authorize($actor, 'update', [$media]);

        if ($media->category !== MediaCategory::PRODUCT_IMAGE->value) {
            throw ValidationException::withMessages([
                'media' => ['Seules les images de produit peuvent être définies comme image principale.'],
            ]);
        }

        return DB::transaction(function () use ($actor, $media): Media {
            $owner = $media->mediable;

            if (! $owner instanceof Model) {
                throw new RuntimeException('Le propriétaire du média est introuvable.');
            }

            $owner = $owner->newQuery()
                ->lockForUpdate()
                ->findOrFail($owner->getKey());

            $lockedMedia = $owner->media()
                ->where('category', MediaCategory::PRODUCT_IMAGE->value)
                ->whereKey($media->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $owner->media()
                ->where('category', MediaCategory::PRODUCT_IMAGE->value)
                ->where('is_primary', true)
                ->where($media->getKeyName(), '!=', $lockedMedia->getKey())
                ->lockForUpdate()
                ->get()
                ->each(function (Media $previous) use ($actor): void {
                    $previous->is_primary = false;
                    $previous->updated_by = (string) $actor->getKey();
                    $previous->save();
                });

            $lockedMedia->is_primary = true;
            $lockedMedia->updated_by = (string) $actor->getKey();
            $lockedMedia->save();

            $this->record($actor, $lockedMedia, 'media.primary_changed', 'primary_changed');

            return $lockedMedia->refresh();
        });
    }

    public function reorder(User $actor, Media $media, int $sortOrder): Media
    {
        Gate::forUser($actor)->authorize('update', $media);

        if ($sortOrder < 0 || $sortOrder > 100_000) {
            throw ValidationException::withMessages([
                'sort_order' => ['L’ordre demandé est invalide.'],
            ]);
        }

        return DB::transaction(function () use ($actor, $media, $sortOrder): Media {
            $owner = $media->mediable;

            if (! $owner instanceof Model) {
                throw new RuntimeException('Le propriétaire du média est introuvable.');
            }

            $owner = $owner->newQuery()
                ->lockForUpdate()
                ->findOrFail($owner->getKey());

            $items = $owner->media()
                ->where('category', $media->category)
                ->orderBy('sort_order')
                ->orderBy('created_at')
                ->lockForUpdate()
                ->get();

            $target = $items->firstWhere($media->getKeyName(), $media->getKey());

            if (! $target instanceof Media) {
                throw new RuntimeException('Le média ne fait plus partie de cette galerie active.');
            }

            $items = $items->reject(
                fn (Media $item): bool => $item->is($target),
            )->values();
            $destination = min($sortOrder, $items->count());
            $items->splice($destination, 0, [$target]);

            foreach ($items as $position => $item) {
                if ($item->sort_order === $position) {
                    continue;
                }

                $item->sort_order = $position;
                $item->updated_by = (string) $actor->getKey();
                $item->save();
                $this->record($actor, $item, 'media.reordered', 'reordered');
            }

            return $target->refresh();
        });
    }

    public function delete(User $actor, Media $media): void
    {
        $this->authorize($actor, 'delete', [$media]);

        DB::transaction(function () use ($actor, $media): void {
            $owner = $media->mediable;

            if (! $owner instanceof Model) {
                throw new RuntimeException('Le propriétaire du média est introuvable.');
            }

            $owner = $owner->newQuery()
                ->lockForUpdate()
                ->findOrFail($owner->getKey());

            $lockedMedia = $owner->media()
                ->whereKey($media->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $wasPrimaryProductImage = $lockedMedia->category === MediaCategory::PRODUCT_IMAGE->value
                && $lockedMedia->is_primary;

            $lockedMedia->deleted_by = (string) $actor->getKey();
            $lockedMedia->save();
            $lockedMedia->delete();

            $this->record($actor, $lockedMedia, 'media.deleted', 'deleted');

            if ($wasPrimaryProductImage && $owner instanceof Model) {
                $replacement = $owner->media()
                    ->where('category', MediaCategory::PRODUCT_IMAGE->value)
                    ->orderBy('sort_order')
                    ->orderBy('created_at')
                    ->first();

                if ($replacement instanceof Media) {
                    $replacement->is_primary = true;
                    $replacement->updated_by = (string) $actor->getKey();
                    $replacement->save();
                    $this->record($actor, $replacement, 'media.primary_changed', 'primary_changed');
                }
            }
        });
    }

    /**
     * Restore a retained media record after verifying its complete file set.
     * Restoration is restricted to root because it can replace the current asset
     * and expose retained historical content.
     */
    public function restore(User $actor, Media $media, bool $makePrimary = false): Media
    {
        $this->authorize($actor, 'restore', [$media]);

        if (! $media->trashed()) {
            throw ValidationException::withMessages([
                'media' => ['Ce média n’est pas supprimé.'],
            ]);
        }

        $owner = $media->mediable;

        if (! $owner instanceof Model || (method_exists($owner, 'trashed') && $owner->trashed())) {
            throw ValidationException::withMessages([
                'media' => ['Le propriétaire doit être restauré avant le média.'],
            ]);
        }

        return DB::transaction(function () use ($actor, $media, $makePrimary): Media {
            $owner = $media->mediable;
            if (! $owner instanceof Model) {
                throw new RuntimeException('Le propriétaire du média est introuvable.');
            }

            $owner = $owner->newQuery()->lockForUpdate()->findOrFail($owner->getKey());
            $locked = Media::withTrashed()->whereKey($media->getKey())->lockForUpdate()->firstOrFail();

            if (! $locked->trashed()) {
                throw ValidationException::withMessages([
                    'media' => ['Ce média a déjà été restauré.'],
                ]);
            }

            $this->assertRetainedFilesAreValid($locked);

            $single = in_array($locked->category, [
                MediaCategory::COOPERATIVE_LOGO->value,
                MediaCategory::PROFILE_IMAGE->value,
            ], true);

            if ($single) {
                $owner->media()->where('category', $locked->category)->lockForUpdate()->get()
                    ->each(function (Media $active) use ($actor, $locked): void {
                        $metadata = $active->metadata ?? [];
                        $metadata['replacement_media_id'] = (string) $locked->getKey();
                        $active->forceFill([
                            'metadata' => $metadata,
                            'deleted_by' => (string) $actor->getKey(),
                        ])->save();
                        $active->delete();
                        $this->record($actor, $active, 'media.replaced_by_restoration', 'replaced_by_restoration');
                    });
            }

            if ($locked->category === MediaCategory::PRODUCT_IMAGE->value && $makePrimary) {
                $owner->media()
                    ->where('category', MediaCategory::PRODUCT_IMAGE->value)
                    ->where('is_primary', true)
                    ->lockForUpdate()
                    ->get()
                    ->each(function (Media $active) use ($actor): void {
                        $active->is_primary = false;
                        $active->updated_by = (string) $actor->getKey();
                        $active->save();
                    });
            }

            $locked->forceFill([
                'deleted_by' => null,
                'updated_by' => (string) $actor->getKey(),
                'is_primary' => $locked->category === MediaCategory::PRODUCT_IMAGE->value
                    ? $makePrimary
                    : $locked->is_primary,
            ])->save();

            $locked->restore();
            $this->record($actor, $locked, 'media.restored', 'restored');

            return $locked->refresh();
        });
    }

    /**
     * Return retained predecessors of a current media item.
     *
     * @return \Illuminate\Support\Collection<int, Media>
     */
    public function replacementHistory(User $actor, Media $current): \Illuminate\Support\Collection
    {
        if (! $actor->hasRole('super_admin_root') || ! $actor->isAuthorized()) {
            $this->recordDeniedAccess($actor, $current);
            abort(403);
        }

        return Media::onlyTrashed()
            ->where('mediable_type', $current->mediable_type)
            ->where('mediable_id', $current->mediable_id)
            ->where('category', $current->category)
            ->where('metadata->replacement_media_id', (string) $current->getKey())
            ->orderByDesc('deleted_at')
            ->get();
    }

    /**
     * Check every retained object before changing database visibility.
     * Missing or malformed objects prevent a partial restore.
     */
    public function isMediaIntegrityValid(Media $media): bool
    {
        try {
            $this->assertRetainedFilesAreValid($media);

            return true;
        } catch (\\Throwable) {
            return false;
        }
    }

    private function assertRetainedFilesAreValid(Media $media): void
    {
        $disk = Storage::disk($media->disk);
        $paths = [$media->path];
        $derivatives = $media->metadata['derivatives'] ?? [];
        $checksums = $media->metadata['checksums'] ?? [];

        if (! in_array($media->category, [
            MediaCategory::DOCUMENT->value,
            MediaCategory::INCIDENT_ATTACHMENT->value,
        ], true)) {
            foreach (['thumbnail', 'small', 'medium', 'large'] as $requiredVariant) {
                if (! is_array($derivatives) || ! isset($derivatives[$requiredVariant])) {
                    throw ValidationException::withMessages([
                        'media' => ['Restauration impossible : un dérivé obligatoire est manquant.'],
                    ]);
                }
            }
        }

        if (is_array($derivatives)) {
            foreach ($derivatives as $path) {
                if (is_string($path)) {
                    $paths[] = $path;
                }
            }
        }

        foreach (array_unique($paths) as $path) {
            if (! is_string($path) || ! $this->isManagedPath($path) || ! $disk->exists($path)) {
                throw ValidationException::withMessages([
                    'media' => ['Restauration impossible : un ou plusieurs fichiers conservés sont absents.'],
                ]);
            }

            $contents = $disk->get($path);
            if (! is_string($contents) || $contents === '') {
                throw ValidationException::withMessages([
                    'media' => ['Restauration impossible : un ou plusieurs fichiers conservés sont illisibles.'],
                ]);
            }

            $expectedChecksum = $path === $media->path
                ? ($checksums['original'] ?? null)
                : ($checksums['derivatives'][array_search($path, $derivatives, true)] ?? null);

            if (is_string($expectedChecksum) && ! hash_equals($expectedChecksum, hash('sha256', $contents))) {
                throw ValidationException::withMessages([
                    'media' => ['Restauration impossible : le contenu d’un fichier conservé a été altéré.'],
                ]);
            }

            if ($path === $media->path) {
                if ($media->mime_type === 'application/pdf') {
                    $valid = str_starts_with($contents, '%PDF-') && str_contains($contents, '%%EOF');
                } else {
                    $image = @getimagesizefromstring($contents);
                    $decoded = @imagecreatefromstring($contents);
                    $valid = is_array($image)
                        && ($image['mime'] ?? null) === $media->mime_type
                        && $decoded instanceof \\GdImage;
                    if ($decoded instanceof \\GdImage) {
                        imagedestroy($decoded);
                    }
                }

                if (! $valid) {
                    throw ValidationException::withMessages([
                        'media' => ['Restauration impossible : le fichier original est corrompu ou invalide.'],
                    ]);
                }
            } else {
                $image = @getimagesizefromstring($contents);
                $decoded = @imagecreatefromstring($contents);
                $expectedMime = $media->metadata['derivative_mime_types'][array_search($path, $derivatives, true)] ?? null;
                $validDerivative = is_array($image)
                    && is_string($expectedMime)
                    && ($image['mime'] ?? null) === $expectedMime
                    && $decoded instanceof \\GdImage;
                if ($decoded instanceof \\GdImage) {
                    imagedestroy($decoded);
                }

                if (! $validDerivative) {
                    throw ValidationException::withMessages([
                        'media' => ['Restauration impossible : un dérivé est corrompu ou invalide.'],
                    ]);
                }
            }
        }
    }

    public function recordPrivateAccess(User $actor, Media $media): void
    {
        $owner = $media->mediable;
        $deletedOwnerDocument = $actor->hasRole('super_admin_root')
            && $media->category === MediaCategory::DOCUMENT->value
            && $owner instanceof Model
            && method_exists($owner, 'trashed')
            && $owner->trashed();

        $this->record(
            $actor,
            $media,
            $deletedOwnerDocument ? 'media.private_accessed_deleted_owner' : 'media.private_accessed',
            $deletedOwnerDocument ? 'private_accessed_deleted_owner' : 'private_accessed',
        );
    }

    public function recordDeniedAccess(User $actor, Media $media): void
    {
        $this->record($actor, $media, 'media.private_access_denied', 'private_access_denied');

        $actorHash = hash('sha256', (string) $actor->getKey());
        $ip = app()->bound('request') ? (string) request()->ip() : 'unknown';
        $ipHash = hash('sha256', $ip);
        $actorKey = 'media:denied:actor:'.$actorHash;
        $ipKey = 'media:denied:ip:'.$ipHash;

        Cache::add($actorKey, 0, now()->addMinutes(15));
        Cache::add($ipKey, 0, now()->addMinutes(15));
        $actorAttempts = (int) Cache::increment($actorKey);
        $ipAttempts = (int) Cache::increment($ipKey);
        $attempts = max($actorAttempts, $ipAttempts);

        if ($attempts >= 5 && ($attempts === 5 || $attempts % 10 === 0)) {
            Log::warning('MEDIA repeated private access denials detected.', [
                'actor_hash' => $actorHash,
                'ip_hash' => $ipHash,
                'attempts' => $attempts,
            ]);

            $roots = User::query()
                ->whereHas('roles', static fn ($query) => $query->where('slug', 'super_admin_root'))
                ->whereNotNull('email')
                ->get();

            try {
                Notification::send(
                    $roots,
                    new MediaSecurityAlertNotification('private_access_denied', $actorHash, $ipHash, $attempts),
                );
            } catch (\\Throwable $exception) {
                Log::error('MEDIA security notification delivery failed.', [
                    'exception' => $exception::class,
                ]);
            }
        }
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

    private function positiveLimit(string $key, int $default): int
    {
        $value = filter_var(config($key, $default), FILTER_VALIDATE_INT);

        return $value !== false && $value > 0 ? $value : $default;
    }

    private function putOrFail(string $disk, string $path, string|false $contents): void
    {
        if (! is_string($contents) || ! Storage::disk($disk)->put($path, $contents)) {
            throw new RuntimeException('Le stockage du média a échoué.');
        }
    }

    private function authorize(User $actor, string $ability, array $arguments): void
    {
        try {
            Gate::forUser($actor)->authorize($ability, $arguments);
        } catch (AuthorizationException $exception) {
            $media = $arguments[0] ?? null;

            if ($media instanceof Media) {
                $this->record($actor, $media, 'media.operation_denied', $ability.'_denied');
            }

            throw $exception;
        }
    }

    private function record(User $actor, Media $media, string $eventKey, string $action): void
    {
        if (! $this->auditEvents->verifyIntegrity()) {
            throw new RuntimeException('La chaîne d’audit est invalide ; opération MEDIA refusée.');
        }

        $owner = $media->mediable;
        $cooperativeId = match (true) {
            $owner instanceof Product => (string) $owner->cooperative_id,
            $owner instanceof Cooperative => (string) $owner->getKey(),
            $owner instanceof User => $owner->cooperative_id === null ? null : (string) $owner->cooperative_id,
            default => null,
        };

        $this->audit->record(new AuditEntryDTO(
            eventKey: $eventKey,
            action: $action,
            actorType: User::class,
            actorId: (string) $actor->getKey(),
            cooperativeId: $cooperativeId,
            subjectType: Media::class,
            subjectId: $media->getKey() === null ? null : (string) $media->getKey(),
            metadata: [
                'category' => $media->category,
                'visibility' => $media->visibility,
                'size' => $media->size,
            ],
        ));
    }
}
