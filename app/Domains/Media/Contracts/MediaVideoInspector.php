<?php

declare(strict_types=1);

namespace App\Domains\Media\Contracts;

interface MediaVideoInspector
{
    /**
     * @return array{
     *     container: string,
     *     duration: float,
     *     width: int,
     *     height: int,
     *     video_codec: string,
     *     audio_codec: string|null
     * }
     */
    public function inspect(string $path): array;
}
