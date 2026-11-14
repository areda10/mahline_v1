<?php

declare(strict_types=1);

namespace App\Domains\Identity\Authentication\DTOs;

use App\Domains\Identity\Authentication\Models\KnownDevice;

final readonly class DeviceTrustResolution
{
    public function __construct(
        public KnownDevice $device,
        public string $cookieToken,
        public bool $isNewDevice,
    ) {}
}
