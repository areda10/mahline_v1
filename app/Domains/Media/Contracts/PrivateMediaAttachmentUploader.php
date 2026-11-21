<?php

declare(strict_types=1);

namespace App\Domains\Media\Contracts;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;

interface PrivateMediaAttachmentUploader
{
    public function upload(string $actorId, Model $owner, UploadedFile $file): string;
}
