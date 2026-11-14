<?php

declare(strict_types=1);

namespace App\Domains\Media\Services;

use Illuminate\Http\UploadedFile;
use RuntimeException;

final class MediaImageProcessor
{
    /**
     * @return array{medium: string, thumbnail: string}
     */
    public function derivatives(UploadedFile $file, string $extension): array
    {
        if (! function_exists('imagecreatefromstring')) {
            throw new RuntimeException('L’extension PHP GD est requise pour traiter les images Media.');
        }

        $contents = file_get_contents($file->getRealPath());

        if (! is_string($contents)) {
            throw new RuntimeException('Impossible de lire le fichier image.');
        }

        $source = @imagecreatefromstring($contents);

        if (! $source instanceof \GdImage) {
            throw new RuntimeException('Le fichier image ne peut pas être décodé.');
        }

        try {
            return [
                'medium' => $this->resize($source, $extension, 1200),
                'thumbnail' => $this->resize($source, $extension, 400),
            ];
        } finally {
            imagedestroy($source);
        }
    }

    private function resize(\GdImage $source, string $extension, int $maxWidth): string
    {
        $width = imagesx($source);
        $height = imagesy($source);
        $ratio = min(1, $maxWidth / max(1, $width));
        $targetWidth = max(1, (int) round($width * $ratio));
        $targetHeight = max(1, (int) round($height * $ratio));
        $target = imagecreatetruecolor($targetWidth, $targetHeight);

        if (! $target instanceof \GdImage) {
            throw new RuntimeException('Impossible de créer une image dérivée.');
        }

        try {
            if ($extension === 'png' || $extension === 'webp') {
                imagealphablending($target, false);
                imagesavealpha($target, true);
                $transparent = imagecolorallocatealpha($target, 0, 0, 0, 127);
                imagefill($target, 0, 0, $transparent);
            } else {
                $white = imagecolorallocate($target, 255, 255, 255);
                imagefill($target, 0, 0, $white);
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

            $success = match ($extension) {
                'jpg', 'jpeg' => imagejpeg($target, null, 85),
                'png' => imagepng($target, null, 6),
                'webp' => function_exists('imagewebp') && imagewebp($target, null, 85),
                default => false,
            };

            $contents = ob_get_clean();

            if (! $success || ! is_string($contents) || $contents === '') {
                throw new RuntimeException('Impossible de générer un dérivé image.');
            }

            return $contents;
        } finally {
            imagedestroy($target);
        }
    }
}
