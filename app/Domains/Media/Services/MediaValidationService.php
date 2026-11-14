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

        if ($path === false) {
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

        if ($category->isImage()) {
            $image = @getimagesize($path);

            if (
                ! is_array($image)
                || ! isset($image['mime'])
                || $image['mime'] !== $mime
                || ($image[0] * $image[1]) > 25_000_000
            ) {
                throw ValidationException::withMessages([
                    'file' => ['L’image est invalide ou ses dimensions sont excessives.'],
                ]);
            }
        } elseif ($mime !== 'application/pdf') {
            throw ValidationException::withMessages([
                'file' => ['Seuls les documents PDF sont autorisés.'],
            ]);
        }

        return [
            'extension' => $extension === 'jpeg' ? 'jpg' : $extension,
            'mime_type' => $mime,
            'size' => $size,
            'original_name' => mb_substr(basename($file->getClientOriginalName()), 0, 255),
        ];
    }
}
