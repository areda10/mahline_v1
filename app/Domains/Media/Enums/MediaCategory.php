<?php

declare(strict_types=1);

namespace App\Domains\Media\Enums;

use App\Domains\Cooperatives\Models\Cooperative;
use App\Domains\Identity\Users\Models\User;
use App\Domains\Media\Models\MediaIncident;
use App\Domains\Products\Models\Product;

enum MediaCategory: string
{
    case PRODUCT_IMAGE = 'product_image';
    case COOPERATIVE_LOGO = 'cooperative_logo';
    case COOPERATIVE_PHOTO = 'cooperative_photo';
    case PROFILE_IMAGE = 'profile_image';
    case DOCUMENT = 'document';
    case INCIDENT_ATTACHMENT = 'incident_attachment';

    public function maxBytes(): int
    {
        return in_array($this, [self::DOCUMENT, self::INCIDENT_ATTACHMENT], true)
            ? 10 * 1024 * 1024
            : 5 * 1024 * 1024;
    }

    public function isImage(): bool
    {
        return ! in_array($this, [self::DOCUMENT, self::INCIDENT_ATTACHMENT], true);
    }

    public function isPublic(): bool
    {
        return in_array($this, [
            self::PRODUCT_IMAGE,
            self::COOPERATIVE_LOGO,
            self::COOPERATIVE_PHOTO,
        ], true);
    }

    public function isSingle(): bool
    {
        return in_array($this, [
            self::COOPERATIVE_LOGO,
            self::PROFILE_IMAGE,
        ], true);
    }

    /** @return array<string, string> */
    public function allowedMimeTypes(): array
    {
        return $this->isImage()
            ? [
                'jpg' => 'image/jpeg',
                'jpeg' => 'image/jpeg',
                'png' => 'image/png',
                'webp' => 'image/webp',
            ]
            : [
                'pdf' => 'application/pdf',
                'jpg' => 'image/jpeg',
                'jpeg' => 'image/jpeg',
                'png' => 'image/png',
            ];
    }

    public function allowsOwner(string $ownerClass): bool
    {
        return match ($this) {
            self::PRODUCT_IMAGE => $ownerClass === Product::class,
            self::COOPERATIVE_LOGO, self::COOPERATIVE_PHOTO => $ownerClass === Cooperative::class,
            self::PROFILE_IMAGE => $ownerClass === User::class,
            self::DOCUMENT => in_array($ownerClass, [
                Product::class,
                Cooperative::class,
                User::class,
            ], true),
            self::INCIDENT_ATTACHMENT => $ownerClass === MediaIncident::class,
        };
    }
}
