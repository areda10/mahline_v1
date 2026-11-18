<?php

declare(strict_types=1);

namespace App\Domains\Media\Http\Controllers;

use App\Domains\Cooperatives\Enums\CooperativeStatus;
use App\Domains\Cooperatives\Models\Cooperative;
use App\Domains\Identity\Users\Models\User;
use App\Domains\Media\Enums\MediaCategory;
use App\Domains\Media\Models\Media;
use App\Domains\Media\Models\MediaIncident;
use App\Domains\Media\Services\MediaService;
use App\Domains\Products\Models\Product;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class MediaController
{
    public function __construct(
        private readonly MediaService $media,
    ) {}

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'owner_type' => ['required', 'string', Rule::in(['product', 'cooperative', 'user', 'incident'])],
            'owner_id' => ['required', 'string', 'size:26'],
            'category' => ['required', 'string', Rule::in([
                'product_image',
                'cooperative_logo',
                'cooperative_photo',
                'profile_image',
                'document',
                'incident_attachment',
            ])],
            'document_category' => ['nullable', 'string', Rule::in(['identity', 'legal', 'administrative', 'certification', 'other'])],
            'file' => ['required', 'file'],
        ]);

        $owner = $this->resolveOwner($data['owner_type'], $data['owner_id']);
        $media = $this->media->upload(
            $request->user(),
            $owner,
            $request->file('file'),
            $data['category'],
            $data['document_category'] ?? null,
        );

        return response()->json([
            'data' => [
                'id' => $media->getKey(),
                'category' => $media->category,
                'collection' => $media->collection,
                'mime_type' => $media->mime_type,
                'size' => $media->size,
                'visibility' => $media->visibility,
                'is_primary' => $media->is_primary,
                'sort_order' => $media->sort_order,
                'show_url' => $media->visibility === 'public'
                    ? route('media.show', $media)
                    : route('media.private', $media),
            ],
        ], 201);
    }

    public function show(Request $request, Media $media): StreamedResponse
    {
        abort_unless(
            $media->visibility === 'public'
            && in_array($media->category, ['product_image', 'cooperative_logo', 'cooperative_photo'], true),
            404,
        );

        $owner = $media->mediable;
        $mediaCategory = MediaCategory::tryFrom($media->category);

        abort_unless(
            $owner instanceof Model
            && ! (method_exists($owner, 'trashed') && $owner->trashed())
            && $mediaCategory?->isPublic()
            && $mediaCategory->allowsOwner($owner::class),
            404,
        );

        $isPublished = match (true) {
            $owner instanceof Product => $owner->is_active
                && $owner->cooperative?->status === CooperativeStatus::ACTIVE
                && $owner->catalogs()
                    ->where('catalogs.cooperative_id', $owner->cooperative_id)
                    ->where('catalogs.is_active', true)
                    ->exists(),
            $owner instanceof Cooperative => $owner->status === CooperativeStatus::ACTIVE,
            default => false,
        };

        abort_unless($isPublished, 404);

        return $this->stream($request, $media);
    }

    public function showPrivate(Request $request, Media $media): StreamedResponse
    {
        abort_unless($media->visibility === 'private', 404);
        $actor = $request->user();

        if (! Gate::forUser($actor)->allows('view', $media)) {
            $this->media->recordDeniedAccess($actor, $media);
            abort(403);
        }

        $this->media->recordPrivateAccess($actor, $media);

        return $this->stream($request, $media);
    }

    private function stream(Request $request, Media $media): StreamedResponse
    {
        $variant = $request->query('variant', 'original');
        abort_unless(in_array($variant, ['original', 'thumbnail', 'small', 'medium', 'large'], true), 404);

        $path = $variant === 'original'
            ? $media->path
            : ($media->metadata['derivatives'][$variant] ?? null);

        abort_unless(is_string($path) && Storage::disk($media->disk)->exists($path), 404);

        $stream = Storage::disk($media->disk)->readStream($path);
        abort_unless(is_resource($stream), 404);

        $mimeType = $variant === 'original'
            ? $media->mime_type
            : ($media->metadata['derivative_mime_types'][$variant] ?? null);

        abort_unless(is_string($mimeType), 404);

        $isDocument = in_array($media->category, ['document', 'incident_attachment'], true);
        $download = $isDocument && $request->boolean('download');
        $filename = preg_replace('/[^A-Za-z0-9._-]/', '_', $media->original_name) ?: 'media';

        return response()->stream(function () use ($stream): void {
            try {
                fpassthru($stream);
            } finally {
                if (is_resource($stream)) {
                    fclose($stream);
                }
            }
        }, 200, [
            'Content-Type' => $mimeType,
            'Content-Disposition' => ($download ? 'attachment' : 'inline').'; filename="'.$filename.'"',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => $media->visibility === 'public'
                ? 'public, no-cache, must-revalidate'
                : 'private, no-store',
        ]);
    }

    public function primary(Request $request, Media $media): JsonResponse
    {
        $media = $this->media->setPrimary($request->user(), $media);

        return response()->json(['data' => ['id' => $media->getKey(), 'is_primary' => true]]);
    }

    public function reorder(Request $request, Media $media): JsonResponse
    {
        $data = $request->validate([
            'sort_order' => ['required', 'integer', 'min:0', 'max:100000'],
        ]);

        $media = $this->media->reorder($request->user(), $media, $data['sort_order']);

        return response()->json(['data' => ['id' => $media->getKey(), 'sort_order' => $media->sort_order]]);
    }

    public function destroy(Request $request, Media $media): JsonResponse
    {
        $this->media->delete($request->user(), $media);

        return response()->json(['message' => 'Le média a été supprimé.']);
    }

    public function restore(Request $request, string $media): JsonResponse
    {
        $record = Media::withTrashed()->findOrFail($media);
        $data = $request->validate([
            'make_primary' => ['sometimes', 'boolean'],
        ]);

        $restored = $this->media->restore(
            $request->user(),
            $record,
            (bool) ($data['make_primary'] ?? false),
        );

        return response()->json([
            'data' => [
                'id' => $restored->getKey(),
                'restored' => true,
                'is_primary' => $restored->is_primary,
            ],
        ]);
    }

    public function ownerHistory(Request $request, string $ownerType, string $ownerId): JsonResponse
    {
        $owner = match ($ownerType) {
            'product' => Product::withTrashed()->findOrFail($ownerId),
            'cooperative' => Cooperative::withTrashed()->findOrFail($ownerId),
            'user' => User::withTrashed()->findOrFail($ownerId),
            'incident' => MediaIncident::query()->findOrFail($ownerId),
            default => abort(404),
        };

        $history = $this->media->ownerHistory($request->user(), $owner);

        return response()->json([
            'data' => $history->map(fn (Media $item): array => [
                'id' => $item->getKey(),
                'category' => $item->category,
                'original_name' => $item->original_name,
                'mime_type' => $item->mime_type,
                'size' => $item->size,
                'deleted_at' => $item->deleted_at?->toIso8601String(),
                'restorable' => $item->trashed()
                    && ! (method_exists($owner, 'trashed') && $owner->trashed())
                    && $this->media->isMediaIntegrityValid($item),
            ])->values(),
        ]);
    }

    public function replacementHistory(Request $request, Media $media): JsonResponse
    {
        $history = $this->media->replacementHistory($request->user(), $media);

        return response()->json([
            'data' => $history->map(static fn (Media $item): array => [
                'id' => $item->getKey(),
                'original_name' => $item->original_name,
                'mime_type' => $item->mime_type,
                'size' => $item->size,
                'deleted_at' => $item->deleted_at?->toIso8601String(),
                'restorable' => $this->media->isMediaIntegrityValid($item),
            ])->values(),
        ]);
    }

    private function resolveOwner(string $type, string $id): Model
    {
        return match ($type) {
            'product' => Product::query()->findOrFail($id),
            'cooperative' => Cooperative::query()->findOrFail($id),
            'user' => User::query()->findOrFail($id),
            'incident' => MediaIncident::query()->findOrFail($id),
        };
    }
}
