<?php

declare(strict_types=1);

namespace App\Domains\Media\Http\Controllers;

use App\Domains\Cooperatives\Models\Cooperative;
use App\Domains\Identity\Authentication\Http\Middleware\EnsureActiveAuthenticationSession;
use App\Domains\Identity\Users\Models\User;
use App\Domains\Media\Models\Media;
use App\Domains\Media\Services\MediaService;
use App\Domains\Products\Models\Product;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class MediaController
{
    public function __construct(
        private readonly MediaService $media,
    ) {}

    public function store(Request $request)
    {
        $data = $request->validate([
            'owner_type' => ['required', 'string', Rule::in(['product', 'cooperative', 'user'])],
            'owner_id' => ['required', 'string', 'size:26'],
            'category' => ['required', 'string', Rule::in([
                'product_image',
                'cooperative_logo',
                'cooperative_photo',
                'profile_image',
                'document',
            ])],
            'document_category' => ['nullable', 'string', 'max:80'],
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
        abort_unless($media->visibility === 'public', 404);

        return $this->stream($request, $media);
    }

    public function showPrivate(Request $request, Media $media): StreamedResponse
    {
        abort_unless($media->visibility === 'private', 404);
        Gate::authorize('view', $media);

        return $this->stream($request, $media);
    }

    private function stream(Request $request, Media $media): StreamedResponse
    {
        $variant = $request->query('variant', 'original');
        abort_unless(in_array($variant, ['original', 'medium', 'thumbnail'], true), 404);

        $path = $media->path;

        if ($variant !== 'original') {
            $derivative = $media->metadata['derivatives'][$variant] ?? null;
            $expected = dirname($media->path).'/'.$variant.'.'.$media->extension;

            abort_unless(is_string($derivative) && $derivative === $expected, 404);

            $path = $derivative;
        }

        abort_unless(Storage::disk($media->disk)->exists($path), 404);

        $stream = Storage::disk($media->disk)->readStream($path);
        abort_unless(is_resource($stream), 404);

        $isDocument = $media->mime_type === 'application/pdf';
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
            'Content-Type' => $media->mime_type,
            'Content-Disposition' => ($isDocument ? 'attachment' : 'inline').'; filename="'.$filename.'"',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => $media->visibility === 'public'
                ? 'public, max-age=3600'
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

    private function resolveOwner(string $type, string $id): Model
    {
        return match ($type) {
            'product' => Product::query()->findOrFail($id),
            'cooperative' => Cooperative::query()->findOrFail($id),
            'user' => User::query()->findOrFail($id),
        };
    }
}
