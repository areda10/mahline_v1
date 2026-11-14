<?php

declare(strict_types=1);

return [
    'public_disk' => env('MEDIA_PUBLIC_DISK', 'public'),
    'private_disk' => env('MEDIA_PRIVATE_DISK', 'local'),
    'retention_days' => 30,
    'collections' => [
        'product_image' => 'product-gallery',
        'cooperative_logo' => 'cooperative-logo',
        'cooperative_photo' => 'cooperative-gallery',
        'profile_image' => 'profile',
        'document' => 'documents',
    ],
];
