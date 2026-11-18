<?php

declare(strict_types=1);

namespace App\Domains\Media\Services;

use Illuminate\Http\UploadedFile;
use RuntimeException;

final class MediaImageProcessor
{
    /**
     * @return array<string, array{contents: string, extension: string, mime_type: string}>
     */
    public function derivatives(UploadedFile $file, string $extension): array
    {
        if (! function_exists('imagecreatefromstring') || ! function_exists('imagewebp')) {
            throw new RuntimeException('PHP GD avec prise en charge WebP est requis pour traiter les images Media V1.');
        }

        $path = $file->getRealPath();

        if (! is_string($path)) {
            throw new RuntimeException('Le fichier image temporaire est inaccessible.');
        }

        $contents = file_get_contents($path);

        if (! is_string($contents)) {
            throw new RuntimeException('Impossible de lire le fichier image.');
        }

        $source = @imagecreatefromstring($contents);

        if (! ($source instanceof \GdImage)) {
            throw new RuntimeException('Le fichier image ne peut pas être décodé.');
        }

        try {
            $source = $this->applyExifOrientation($source, $path, $extension);
            $outputExtension = match ($extension) {
                'png' => $this->pngHasTransparency($contents) ? 'png' : 'webp',
                'jpg', 'jpeg', 'webp' => 'webp',
                default => throw new RuntimeException('Format image non pris en charge pour les dérivés.'),
            };
            $mimeType = $outputExtension === 'png' ? 'image/png' : 'image/webp';

            $sizes = [
                'thumbnail' => [150, 150],
                'small' => [300, 300],
                'medium' => [600, 600],
                'large' => [1600, 1600],
            ];
            $derivatives = [];

            foreach ($sizes as $name => [$maxWidth, $maxHeight]) {
                $derivatives[$name] = [
                    'contents' => $this->resize($source, $outputExtension, $maxWidth, $maxHeight),
                    'extension' => $outputExtension,
                    'mime_type' => $mimeType,
                ];
            }

            return $derivatives;
        } finally {
            imagedestroy($source);
        }
    }

    private function applyExifOrientation(\GdImage $source, string $path, string $extension): \GdImage
    {
        imagealphablending($source, false);
        imagesavealpha($source, true);

        if (! in_array($extension, ['jpg', 'jpeg'], true) || ! function_exists('exif_read_data')) {
            return $source;
        }

        $exif = @exif_read_data($path);
        $orientation = (int) ($exif['Orientation'] ?? 1);
        $rotated = null;

        if (in_array($orientation, [3, 5, 6, 7, 8], true)) {
            $angle = match ($orientation) {
                3, 4 => 180,
                5, 8 => 90,
                6, 7 => -90,
            };
            $rotated = imagerotate($source, $angle, imagecolorallocatealpha($source, 0, 0, 0, 127));

            if (! ($rotated instanceof \GdImage)) {
                throw new RuntimeException('Impossible de corriger l’orientation EXIF de l’image.');
            }

            imagedestroy($source);
            $source = $rotated;
        }

        if (in_array($orientation, [2, 4, 5, 7], true)) {
            if (! imageflip($source, IMG_FLIP_HORIZONTAL)) {
                throw new RuntimeException('Impossible de corriger l’orientation EXIF de l’image.');
            }
        }

        imagealphablending($source, false);
        imagesavealpha($source, true);

        return $source;
    }

    private function pngHasTransparency(string $contents): bool
    {
        if (strlen($contents) < 26 || substr($contents, 0, 8) !== "\x89PNG\r\n\x1a\n") {
            return false;
        }

        // PNG color types 4 and 6 carry an alpha channel; tRNS adds transparency to indexed/RGB images.
        $colorType = ord($contents[25]);

        return in_array($colorType, [4, 6], true) || str_contains($contents, 'tRNS');
    }

    private function resize(\GdImage $source, string $extension, int $maxWidth, int $maxHeight): string
    {
        $width = imagesx($source);
        $height = imagesy($source);
        $ratio = min(1, $maxWidth / max(1, $width), $maxHeight / max(1, $height));
        $targetWidth = max(1, (int) round($width * $ratio));
        $targetHeight = max(1, (int) round($height * $ratio));
        $target = imagecreatetruecolor($targetWidth, $targetHeight);

        if (! ($target instanceof \GdImage)) {
            throw new RuntimeException('Impossible de créer une image dérivée.');
        }

        try {
            if ($extension === 'png') {
                imagealphablending($target, false);
                imagesavealpha($target, true);
                $transparent = imagecolorallocatealpha($target, 0, 0, 0, 127);
                imagefill($target, 0, 0, $transparent);
            }

            imagecopyresampled(
                $target,
                $source,
                0,
                0,
                0,
                0,
                $targetWidth,
                $targetHeight,
                $width,
                $height,
            );

            ob_start();

            $success = $extension === 'png'
                ? imagepng($target, null, 6)
                : imagewebp($target, null, 85);

            $derivative = ob_get_clean();

            if (! $success || ! is_string($derivative) || $derivative === '') {
                throw new RuntimeException('Impossible de générer un dérivé image.');
            }

            return $derivative;
        } finally {
            imagedestroy($target);
        }
    }
}
