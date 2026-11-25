<?php

declare(strict_types=1);

namespace App\Domains\Media\Services;

use App\Domains\Media\Contracts\MediaVideoInspector;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;

final class MediaVideoValidationService
{
    private const ALLOWED_CONTAINERS = [
        // FFprobe reports QuickTime/MP4-family containers as a comma-separated list.
        // The MIME check below distinguishes MOV from MP4 uploads.
        'mp4' => ['mp4', 'mov', 'm4a', '3gp', '3g2', 'mj2'],
        'mov' => ['mov', 'mp4', 'm4a', '3gp', '3g2', 'mj2'],
        'webm' => ['webm'],
    ];

    public function __construct(
        private readonly MediaVideoInspector $inspector,
    ) {}

    /**
     * @return array{
     *     extension: string,
     *     mime_type: string,
     *     size: int,
     *     original_name: string,
     *     video: array<string, string|int|float|null>
     * }
     */
    public function validate(UploadedFile $file): array
    {
        if (! $file->isValid()) {
            $this->reject('Le fichier vidéo transmis est invalide ou incomplet.');
        }

        $extension = strtolower($file->getClientOriginalExtension());
        $mimeTypes = [
            'mp4' => 'video/mp4',
            'mov' => 'video/quicktime',
            'webm' => 'video/webm',
        ];

        if (! isset(self::ALLOWED_CONTAINERS[$extension])) {
            $this->reject('Seules les vidéos MP4, MOV et WebM sont acceptées.');
        }

        $size = (int) $file->getSize();
        $maxBytes = (int) config('media.video.max_bytes', 20 * 1024 * 1024);

        if ($size < 1 || $size > $maxBytes) {
            $this->reject('La vidéo dépasse la limite de taille autorisée de 20 Mo.');
        }

        $path = $file->getRealPath();

        if (! is_string($path) || ! is_readable($path)) {
            $this->reject('Le fichier vidéo temporaire est inaccessible.');
        }

        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($path);

        // Certains builds de Fileinfo (notamment Windows) classent les petits
        // fichiers vidéo ou certains conteneurs comme application/octet-stream.
        // Dans ce cas uniquement, on exige une signature binaire cohérente puis
        // on confirme le conteneur avec l'inspecteur vidéo avant d'accepter.
        $mimeIsExact = is_string($mime) && $mime === $mimeTypes[$extension];
        $mimeIsGeneric = $mime === 'application/octet-stream';

        if (! $mimeIsExact && (! $mimeIsGeneric || ! $this->hasExpectedContainerSignature($path, $extension))) {
            $this->reject('Le contenu réel de la vidéo ne correspond pas à son extension.');
        }

        try {
            $metadata = $this->inspector->inspect($path);
        } catch (\Throwable) {
            $this->reject('La vidéo est illisible ou ses métadonnées ne peuvent pas être vérifiées.');
        }

        $allowedContainers = self::ALLOWED_CONTAINERS[$extension];
        $actualContainers = explode(',', $metadata['container']);

        if (count(array_intersect($allowedContainers, $actualContainers)) === 0) {
            $this->reject('Le conteneur vidéo réel ne correspond pas au format annoncé.');
        }

        $maxDuration = (float) config('media.video.max_duration_seconds', 60);
        $maxResolution = (int) config('media.video.max_resolution', 1080);

        if ($metadata['duration'] > $maxDuration) {
            $this->reject('La durée maximale autorisée pour une vidéo est de 60 secondes.');
        }

        $longSide = max((int) $metadata['width'], (int) $metadata['height']);
        $shortSide = min((int) $metadata['width'], (int) $metadata['height']);
        $maxLongSide = (int) floor($maxResolution * 16 / 9);

        if ($longSide > $maxLongSide || $shortSide > $maxResolution) {
            $this->reject('La résolution maximale autorisée pour une vidéo est de 1080p.');
        }

        return [
            'extension' => $extension,
            'mime_type' => $mime,
            'size' => $size,
            'original_name' => mb_substr(basename($file->getClientOriginalName()), 0, 255),
            'video' => $metadata,
        ];
    }

    private function hasExpectedContainerSignature(string $path, string $extension): bool
    {
        $handle = @fopen($path, 'rb');

        if ($handle === false) {
            return false;
        }

        try {
            $header = fread($handle, 64);
        } finally {
            fclose($handle);
        }

        if (! is_string($header)) {
            return false;
        }

        return match ($extension) {
            'webm' => str_starts_with($header, "\x1A\x45\xDF\xA3"),
            'mov' => str_contains($header, 'ftypqt  '),
            'mp4' => str_contains($header, 'ftyp')
                && preg_match('/ftyp(?:isom|iso[2-9]|mp4[12]|avc1|M4V |MSNV|dash)/', $header) === 1,
            default => false,
        };
    }

    private function reject(string $message): never
    {
        throw ValidationException::withMessages(['file' => [$message]]);
    }
}
