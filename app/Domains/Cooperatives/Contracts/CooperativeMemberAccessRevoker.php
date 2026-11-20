<?php

declare(strict_types=1);

namespace App\Domains\Cooperatives\Contracts;

interface CooperativeMemberAccessRevoker
{
    /**
     * Revoke all web sessions and API tokens for cooperative_admin and user
     * accounts owned by the cooperative. Account statuses remain unchanged.
     *
     * @return array{users: int, sessions: int, tokens: int}
     */
    public function revokeMemberAccess(string $cooperativeId, string $reason): array;
}
