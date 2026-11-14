<?php

declare(strict_types=1);

namespace App\Domains\Media\Enums;

enum MediaCategory: string
{
    case PRODUCT_IMAGE = 'product_image';
    case COOPERATIVE_LOGO = 'cooperative_logo';
    case COOPERATIVE_PHOTO = 'cooperative_photo';
    case PROFILE_IMAGE = 'profile_image';
    case DOCUMENT = 'document';

    public function maxBytes(): int
    {
        return match ($this) {
            self::PRODUCT_IMAGE, self::COOPERATIVE_LOGO, self::COOPERATIVE_PHOTO => 5 * 1024 * 1024,
            self::PROFILE_IMAGE => 2 * 1024 * 1024,
            self::DOCUMENT => 15 * 1024 * 1024,
        };
    }

    public function isImage(): bool
    {
        return $this !== self::DOCUMENT;
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
            : ['pdf' => 'application/pdf'];
    }

    public function allowsOwner(string $ownerClass): bool
    {
        return match ($this) {
            self::PRODUCT_IMAGE => $ownerClass === \App\Domains\Products\Models\Product::class,
            self::COOPERATIVE_LOGO, self::COOPERATIVE_PHOTO => $ownerClass === \App\Domains\Cooperatives\Models\Cooperative::class,
            self::PROFILE_IMAGE => $ownerClass === \App\Domains\Identity\Users\Models\User::class,
            self::DOCUMENT => in_array($ownerClass, [
                \App\Domains\Products\Models\Product::class,
                \App\Domains\Cooperatives\Models\Cooperative::class,
                \App\Domains\Identity\Users\Models\User::class,
            ], true),
        };
    }
}
