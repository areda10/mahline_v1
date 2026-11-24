<?php

declare(strict_types=1);

return [
    'public_disk' => env('MEDIA_PUBLIC_DISK', 'local'),
    'private_disk' => env('MEDIA_PRIVATE_DISK', 'local'),
    'retention_days' => env('MEDIA_RETENTION_DAYS', 90),
    'orphan_safety_hours' => env('MEDIA_ORPHAN_SAFETY_HOURS', 24),
    'video' => [
        'ffmpeg_binary' => env('MEDIA_FFMPEG_BINARY', 'ffmpeg'),
        'ffprobe_binary' => env('MEDIA_FFPROBE_BINARY', 'ffprobe'),
        'probe_timeout_seconds' => env('MEDIA_VIDEO_PROBE_TIMEOUT', 15),
        'max_bytes' => env('MEDIA_VIDEO_MAX_BYTES', 20 * 1024 * 1024),
        'max_duration_seconds' => env('MEDIA_VIDEO_MAX_DURATION', 60),
        'max_resolution' => env('MEDIA_VIDEO_MAX_RESOLUTION', 1080),
        'automatic_attempts' => env('MEDIA_VIDEO_MAX_ATTEMPTS', 3),
        'original_retention_hours' => env('MEDIA_VIDEO_ORIGINAL_RETENTION_HOURS', 24),
    ],
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
