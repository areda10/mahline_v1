<?php

declare(strict_types=1);

return [
    'public_disk' => env('MEDIA_PUBLIC_DISK', 'local'),
    'private_disk' => env('MEDIA_PRIVATE_DISK', 'local'),
    'retention_days' => env('MEDIA_RETENTION_DAYS', 90),
    'orphan_safety_hours' => env('MEDIA_ORPHAN_SAFETY_HOURS', 24),
    'limits' => [
        'product_gallery' => env('MEDIA_PRODUCT_GALLERY_LIMIT', 10),
        'documents_per_owner' => env('MEDIA_DOCUMENTS_PER_OWNER_LIMIT', 20),
    ],
    'collections' => [
        'product_image' => 'product-gallery',
        'cooperative_logo' => 'cooperative-logo',
        'cooperative_photo' => 'cooperative-gallery',
        'profile_image' => 'profile',
        'document' => 'documents',
    ],
];
