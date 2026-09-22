<?php

declare(strict_types=1);

namespace App\Domains\Identity\Authentication\Services;

use App\Core\Foundation\Services\BaseService;
use App\Domains\Identity\Authentication\Models\KnownDevice;
use App\Domains\Identity\Users\Models\User;
use InvalidArgumentException;

final class UnusualActivityDetectionService extends BaseService
{

    public function isNewDevice(
        User $user,
        string $device,
    ): bool {
        $device = $this->normalizeDevice($device);
        
        if ($device === '') {
            throw new InvalidArgumentException(
                'Device cannot be empty.',
            );
        }

        return ! KnownDevice::query()
            ->where('user_id', $user->id)
            ->where('device', $device)
            ->exists();
    }

    public function rememberDevice(
        User $user,
        string $device,
    ): void {
        $device = $this->normalizeDevice($device);
        
        if ($device === '') {
            throw new InvalidArgumentException(
                'Device cannot be empty.',
            );
        }
        KnownDevice::query()->firstOrCreate([
            'user_id' => $user->id,
            'device' => $device,
        ]);
    }

    private function normalizeDevice(string $device): string
    {
        return mb_strtolower(trim($device));
    }
}