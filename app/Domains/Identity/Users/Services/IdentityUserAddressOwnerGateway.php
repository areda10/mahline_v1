<?php

declare(strict_types=1);

namespace App\Domains\Identity\Users\Services;

use App\Domains\Identity\Users\Models\User;
use App\Domains\Localization\Contracts\UserAddressOwnerGateway;
use App\Domains\Localization\DTOs\UserAddressOwnerData;
use App\Domains\Localization\Policies\AddressPolicy;
use App\Shared\Contracts\ActorContextProvider;
use Illuminate\Auth\Access\AuthorizationException;

final class IdentityUserAddressOwnerGateway implements UserAddressOwnerGateway
{
    public function __construct(
        private readonly ActorContextProvider $actors,
        private readonly AddressPolicy $policy,
    ) {}

    public function lock(string $userId): UserAddressOwnerData
    {
        $user = User::query()->whereKey($userId)->lockForUpdate()->firstOrFail();

        return $this->ownerData($user);
    }

    public function authorize(string $actorId, string $ability, string $userId): void
    {
        if ($ability !== 'updateUserAddress') {
            throw new \InvalidArgumentException('Unsupported user address authorization ability.');
        }

        $actor = $this->actors->forUserId($actorId);
        $owner = User::query()->with('roles')->findOrFail($userId);

        if (! $this->policy->updateUserAddress($actor, $this->ownerData($owner))) {
            throw new AuthorizationException;
        }
    }

    public function cooperativeId(string $userId): ?string
    {
        $value = User::query()->whereKey($userId)->value('cooperative_id');

        return $value === null ? null : (string) $value;
    }

    private function ownerData(User $user): UserAddressOwnerData
    {
        $roles = $user->roles
            ->filter(static fn ($role): bool => $role->is_active)
            ->pluck('slug')
            ->unique()
            ->values()
            ->all();

        return new UserAddressOwnerData(
            id: (string) $user->getKey(),
            cooperativeId: $user->cooperative_id === null ? null : (string) $user->cooperative_id,
            isCustomer: in_array('customer', $roles, true),
            roles: $roles,
        );
    }
}
