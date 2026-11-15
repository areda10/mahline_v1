<?php

declare(strict_types=1);

namespace App\Domains\Identity\Authentication\Support;

final class DeviceTrustCookie
{
    public const string NAME = 'mahline_device_trust';

    public const string REQUEST_ATTRIBUTE = 'identity.device_trust_cookie';

    public static function isValid(?string $token): bool
    {
        return $token !== null
            && preg_match('/\A[a-f0-9]{64}\z/D', $token) === 1;
    }

    public static function hash(string $token): string
    {
        return hash('sha256', $token);
    }
}
