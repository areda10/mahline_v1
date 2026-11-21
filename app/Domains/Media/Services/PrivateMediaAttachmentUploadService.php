<?php

declare(strict_types=1);

namespace App\Domains\Media\Services;

use App\Domains\Identity\Users\Models\User;
use App\Domains\Media\Contracts\PrivateMediaAttachmentUploader;
use App\Domains\Media\Enums\MediaCategory;
use App\Shared\Contracts\PrivateMediaAttachmentOwner;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;

final class PrivateMediaAttachmentUploadService implements PrivateMediaAttachmentUploader
{
    public function __construct(private readonly MediaService $media) {}

    public function upload(string $actorId, Model $owner, UploadedFile $file): string
    {
        if (! $owner instanceof PrivateMediaAttachmentOwner) {
            throw new AuthorizationException('This entity does not accept private incident attachments.');
        }

        $actor = User::query()->findOrFail($actorId);
        $uploaded = $this->media->upload(
            $actor,
            $owner,
            $file,
            MediaCategory::INCIDENT_ATTACHMENT->value,
        );

        return (string) $uploaded->getKey();
    }
}
