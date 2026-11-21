<?php

declare(strict_types=1);

namespace App\Shared\Contracts;

interface PrivateMediaAttachmentOwner
{
    public function privateMediaCooperativeId(): string;

    public function privateMediaReporterId(): string;

    public function privateMediaUploadOpen(): bool;
}
