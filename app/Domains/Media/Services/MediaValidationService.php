<?php

declare(strict_types=1);

namespace App\Domains\Media\Services;

use App\Domains\Media\Enums\MediaCategory;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;

final class MediaValidationService
{
    /**
     * @return array{extension: string, mime_type: string, size: int, original_name: string}
     */
    public function validate(UploadedFile $file, MediaCategory $category): array
    {
        if (! $file->isValid()) {
            throw ValidationException::withMessages([
                'file' => ['Le fichier transmis est invalide ou incomplet.'],
            ]);
        }

        $extension = strtolower($file->getClientOriginalExtension());
        $allowed = $category->allowedMimeTypes();

        if (! array_key_exists($extension, $allowed)) {
            throw ValidationException::withMessages([
                'file' => ['Le format du fichier n’est pas autorisé pour cette catégorie.'],
            ]);
        }

        $size = (int) $file->getSize();

        if ($size < 1 || $size > $category->maxBytes()) {
            throw ValidationException::withMessages([
                'file' => ['La taille du fichier dépasse la limite autorisée.'],
            ]);
        }

        $path = $file->getRealPath();

        if (! is_string($path)) {
            throw ValidationException::withMessages([
                'file' => ['Le fichier temporaire est inaccessible.'],
            ]);
        }

        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($path);

        if (! is_string($mime) || $mime !== $allowed[$extension]) {
            throw ValidationException::withMessages([
                'file' => ['Le contenu réel du fichier ne correspond pas à son extension.'],
            ]);
        }

        if ($category->isImage() || str_starts_with($mime, 'image/')) {
            $this->validateImageContent($path, $mime, $extension);
        } elseif (
            $mime !== 'application/pdf'
            || ! str_starts_with((string) file_get_contents($path, false, null, 0, 8), '%PDF-')
        ) {
            throw ValidationException::withMessages([
                'file' => ['Le document PDF est invalide.'],
            ]);
        }

        return [
            'extension' => $extension === 'jpeg' ? 'jpg' : $extension,
            'mime_type' => $mime,
            'size' => $size,
            'original_name' => mb_substr(basename($file->getClientOriginalName()), 0, 255),
        ];
    }

    private function validateImageContent(string $path, string $mime, string $extension): void
    {
        $image = @getimagesize($path);

        if (
            ! is_array($image)
            || ! isset($image['mime'], $image[0], $image[1])
            || $image['mime'] !== $mime
            || $image[0] < 1
            || $image[1] < 1
            || $image[0] > 4000
            || $image[1] > 4000
            || ($image[0] * $image[1]) > 16_000_000
        ) {
            throw ValidationException::withMessages([
                'file' => ['L’image est invalide ou dépasse les dimensions maximales de 4000 × 4000 pixels.'],
            ]);
        }

        if ($extension === 'webp' && $this->isAnimatedWebp($path)) {
            throw ValidationException::withMessages([
                'file' => ['Les images animées ne sont pas autorisées en V1.'],
            ]);
        }
    }

    private function isAnimatedWebp(string $path): bool
    {
        $contents = file_get_contents($path);

        if (
            ! is_string($contents)
            || strlen($contents) < 12
            || substr($contents, 0, 4) !== 'RIFF'
            || substr($contents, 8, 4) !== 'WEBP'
        ) {
            return false;
        }

        $length = strlen($contents);
        $offset = 12;

        while ($offset + 8 <= $length) {
            $chunk = substr($contents, $offset, 4);
            $chunkSizeData = unpack('Vsize', substr($contents, $offset + 4, 4));
            $chunkSize = (int) ($chunkSizeData['size'] ?? 0);
            $dataOffset = $offset + 8;

            if ($dataOffset + $chunkSize > $length) {
                return false;
            }

            if ($chunk === 'ANIM' || $chunk === 'ANMF') {
                return true;
            }

            if ($chunk === 'VP8X' && $chunkSize >= 1) {
                $flags = ord($contents[$dataOffset]);

                if (($flags & 0x02) !== 0) {
                    return true;
                }
            }

            $offset = $dataOffset + $chunkSize + ($chunkSize % 2);
        }

        return false;
    }
}
