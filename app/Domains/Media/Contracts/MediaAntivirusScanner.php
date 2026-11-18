<?php

declare(strict_types=1);

namespace App\Domains\Media\Contracts;

use Illuminate\Http\UploadedFile;

interface MediaAntivirusScanner
{
    public function scan(UploadedFile $file): void;
}
