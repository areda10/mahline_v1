<?php

declare(strict_types=1);

namespace App\Domains\Identity\Users\Services;

use App\Domains\Identity\Users\Models\User;
use App\Domains\Localization\Contracts\UserAddressOwnerGateway;
use App\Domains\Localization\DTOs\UserAddressOwnerData;
use Illuminate\Support\Facades\Gate;

final class IdentityUserAddressOwnerGateway implements UserAddressOwnerGateway
{
    public function lock(string $userId): UserAddressOwnerData
    {
        $user = User::query()->whereKey($userId)->lockForUpdate()->firstOrFail();

        return new UserAddressOwnerData(
            id: (string) $user->getKey(),
            cooperativeId: $user->cooperative_id === null ? null : (string) $user->cooperative_id,
            isCustomer: $user->roles()
                ->where('slug', 'customer')
                ->where('is_active', true)
                ->exists(),
        );
    }

    public function authorize(string $actorId, string $ability, string $userId): void
    {
        $actor = User::query()->findOrFail($actorId);
        $owner = User::query()->findOrFail($userId);

        Gate::forUser($actor)->authorize($ability, $owner);
    }

    public function cooperativeId(string $userId): ?string
    {
        $value = User::query()->whereKey($userId)->value('cooperative_id');

        return $value === null ? null : (string) $value;
    }
}
