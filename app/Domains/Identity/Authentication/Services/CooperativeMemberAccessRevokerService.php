<?php

declare(strict_types=1);

namespace App\Domains\Identity\Authentication\Services;

use App\Domains\Cooperatives\Contracts\CooperativeMemberAccessRevoker;
use App\Domains\Identity\Users\Models\User;
use Illuminate\Support\Facades\DB;

final class CooperativeMemberAccessRevokerService implements CooperativeMemberAccessRevoker
{
    public function __construct(
        private readonly SessionService $sessions,
    ) {}

    public function revokeMemberAccess(string $cooperativeId, string $reason): array
    {
        if (trim($reason) === '') {
            throw new \InvalidArgumentException('A cooperative access revocation reason is required.');
        }

        return DB::transaction(function () use ($cooperativeId, $reason): array {
            $members = User::query()
                ->where('cooperative_id', $cooperativeId)
                ->whereHas('roles', static fn ($roles) => $roles
                    ->where('roles.is_active', true)
                    ->whereIn('roles.slug', ['cooperative_admin', 'user']))
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            $totals = ['users' => 0, 'sessions' => 0, 'tokens' => 0];

            foreach ($members as $member) {
                $revoked = $this->sessions->revokeAllAccessForUser($member, $reason);
                $totals['users']++;
                $totals['sessions'] += $revoked['sessions'];
                $totals['tokens'] += $revoked['tokens'];
            }

            return $totals;
        });
    }
}
