<?php

declare(strict_types=1);

namespace App\\Domains\\Media\\Services;

use App\\Domains\\Media\\Contracts\\MediaAntivirusScanner as MediaAntivirusScannerContract;
use Illuminate\\Http\\UploadedFile;
use Illuminate\\Support\\Facades\\Process;
use Illuminate\\Validation\\ValidationException;
use RuntimeException;

final class MediaAntivirusScanner implements MediaAntivirusScannerContract
{
    public function scan(UploadedFile $file): void
    {
        if (! $file->isValid() || ! is_string($file->getRealPath())) {
            throw ValidationException::withMessages([
                'file' => ['Le fichier à analyser est inaccessible.'],
            ]);
        }

        $binary = trim((string) config('media.antivirus_binary', 'clamdscan'));

        if ($binary === '') {
            throw new RuntimeException('Le moteur antivirus MEDIA n’est pas configuré.');
        }

        try {
            $result = Process::timeout(45)->run([$binary, '--no-summary', $file->getRealPath()]);
        } catch (\\Throwable $exception) {
            throw new RuntimeException('Le contrôle antivirus MEDIA est indisponible ; fichier refusé.', previous: $exception);
        }

        if ($result->exitCode() === 1) {
            throw ValidationException::withMessages([
                'file' => ['Le fichier a été refusé par le contrôle antivirus.'],
            ]);
        }

        if (! $result->successful()) {
            throw new RuntimeException('Le contrôle antivirus MEDIA a échoué ; fichier refusé.');
        }
    }
}
