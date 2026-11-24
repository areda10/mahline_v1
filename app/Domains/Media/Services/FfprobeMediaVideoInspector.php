<?php

declare(strict_types=1);

namespace App\Domains\Media\Services;

use App\Domains\Media\Contracts\MediaVideoInspector;
use RuntimeException;
use Symfony\Component\Process\Process;
use Throwable;

final class FfprobeMediaVideoInspector implements MediaVideoInspector
{
    public function inspect(string $path): array
    {
        $binary = (string) config('media.video.ffprobe_binary', 'ffprobe');

        if ($binary === '' || ! is_file($path) || ! is_readable($path)) {
            throw new RuntimeException('Le fichier vidéo ou le binaire FFprobe est inaccessible.');
        }

        try {
            $process = new Process([
                $binary,
                '-v', 'error',
                '-show_entries', 'format=format_name,duration:stream=codec_type,codec_name,width,height',
                '-of', 'json',
                $path,
            ]);
            $process->setTimeout((float) config('media.video.probe_timeout_seconds', 15));
            $process->run();
        } catch (Throwable $exception) {
            throw new RuntimeException('Impossible d’exécuter FFprobe.', previous: $exception);
        }

        if (! $process->isSuccessful()) {
            throw new RuntimeException('FFprobe n’a pas pu lire les métadonnées de cette vidéo.');
        }

        try {
            $payload = json_decode($process->getOutput(), true, 512, JSON_THROW_ON_ERROR);
        } catch (Throwable $exception) {
            throw new RuntimeException('FFprobe a retourné des métadonnées invalides.', previous: $exception);
        }

        $format = $payload['format'] ?? null;
        $streams = $payload['streams'] ?? null;

        if (! is_array($format) || ! is_array($streams)) {
            throw new RuntimeException('Les métadonnées vidéo sont incomplètes.');
        }

        $video = null;
        $audio = null;

        foreach ($streams as $stream) {
            if (! is_array($stream)) {
                continue;
            }

            if (($stream['codec_type'] ?? null) === 'video' && $video === null) {
                $video = $stream;
            }

            if (($stream['codec_type'] ?? null) === 'audio' && $audio === null) {
                $audio = $stream;
            }
        }

        $duration = filter_var($format['duration'] ?? null, FILTER_VALIDATE_FLOAT);
        $width = filter_var($video['width'] ?? null, FILTER_VALIDATE_INT);
        $height = filter_var($video['height'] ?? null, FILTER_VALIDATE_INT);
        $container = $format['format_name'] ?? null;
        $videoCodec = $video['codec_name'] ?? null;
        $audioCodec = $audio['codec_name'] ?? null;

        if (
            $duration === false || $duration <= 0
            || $width === false || $width < 1
            || $height === false || $height < 1
            || ! is_string($container) || $container === ''
            || ! is_string($videoCodec) || $videoCodec === ''
            || ($audioCodec !== null && ! is_string($audioCodec))
        ) {
            throw new RuntimeException('Les métadonnées vidéo sont invalides ou incomplètes.');
        }

        return [
            'container' => strtolower($container),
            'duration' => (float) $duration,
            'width' => $width,
            'height' => $height,
            'video_codec' => strtolower($videoCodec),
            'audio_codec' => is_string($audioCodec) ? strtolower($audioCodec) : null,
        ];
    }
}
