<?php

declare(strict_types=1);

namespace App\Domains\Media\Services;

use App\Domains\Audit\Services\AuditService;
use App\Domains\Cooperatives\Models\Cooperative;
use App\Domains\Identity\Users\Models\User;
use App\Domains\Media\Enums\MediaCategory;
use App\Domains\Media\Enums\MediaDocumentCategory;
use App\Domains\Media\Models\Media;
use App\Domains\Products\Models\Product;
use App\Shared\DTOs\AuditEntryDTO;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
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

        Gate::forUser($actor)->authorize('upload', [new Media, $owner, $category]);

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
        $mediaId = (string) Str::ulid();
        $visibility = $mediaCategory->isPublic() ? 'public' : 'private';
        $disk = (string) config(
            $visibility === 'public' ? 'media.public_disk' : 'media.private_disk',
        );
        $directory = 'media/'.$mediaCategory->value.'/'.$owner->getKey().'/'.$mediaId;
        $path = $directory.'/original.'.$validated['extension'];
        $storedPaths = [];

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

            $derivativePaths = [];

            foreach ($derivatives as $name => $contents) {
                $derivativePath = $directory.'/'.$name.'.'.$validated['extension'];
                $storedPaths[] = $derivativePath;
                $this->putOrFail($disk, $derivativePath, $contents);
                $derivativePaths[$name] = $derivativePath;
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
                $mediaId,
            ): Media {
                $lockedOwner = $owner->newQuery()
                    ->lockForUpdate()
                    ->findOrFail($owner->getKey());

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
                        ->each(function (Media $previous) use ($actor): void {
                            $previous->deleted_by = (string) $actor->getKey();
                            $previous->save();
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
                    Storage::disk($disk)->delete($storedPath);
                } catch (\Throwable) {
                    // Preserve the original upload/persistence exception.
                }
            }

            throw $exception;
        }
    }

    public function setPrimary(User $actor, Media $media): Media
    {
        Gate::forUser($actor)->authorize('update', $media);

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

            $owner->media()
                ->where('category', MediaCategory::PRODUCT_IMAGE->value)
                ->where('is_primary', true)
                ->where($media->getKeyName(), '!=', $media->getKey())
                ->update([
                    'is_primary' => false,
                    'updated_by' => (string) $actor->getKey(),
                ]);

            $media->is_primary = true;
            $media->updated_by = (string) $actor->getKey();
            $media->save();

            $this->record($actor, $media, 'media.primary_changed', 'primary_changed');

            return $media->refresh();
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

        $media->sort_order = $sortOrder;
        $media->updated_by = (string) $actor->getKey();
        $media->save();

        $this->record($actor, $media, 'media.reordered', 'reordered');

        return $media->refresh();
    }

    public function delete(User $actor, Media $media): void
    {
        Gate::forUser($actor)->authorize('delete', $media);

        DB::transaction(function () use ($actor, $media): void {
            $owner = $media->mediable;

            if ($owner instanceof Model) {
                $owner = $owner->newQuery()
                    ->lockForUpdate()
                    ->findOrFail($owner->getKey());
            }

            $wasPrimaryProductImage = $media->category === MediaCategory::PRODUCT_IMAGE->value
                && $media->is_primary;

            $media->deleted_by = (string) $actor->getKey();
            $media->save();
            $media->delete();

            $this->record($actor, $media, 'media.deleted', 'deleted');

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

    private function putOrFail(string $disk, string $path, string|false $contents): void
    {
        if (! is_string($contents) || ! Storage::disk($disk)->put($path, $contents)) {
            throw new RuntimeException('Le stockage du média a échoué.');
        }
    }

    private function record(User $actor, Media $media, string $eventKey, string $action): void
    {
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
            subjectId: (string) $media->getKey(),
            metadata: [
                'category' => $media->category,
                'visibility' => $media->visibility,
                'size' => $media->size,
            ],
        ));
    }
}
