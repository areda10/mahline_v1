<?php

declare(strict_types=1);

namespace App\Domains\Audit\Policies;

use App\Core\Foundation\Policies\BasePolicy;
use App\Domains\Audit\Models\AuditEvent;
use App\Domains\Identity\Users\Models\User;

final class AuditEventPolicy extends BasePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAuthorized()
            && $user->hasPermission('audit.view');
    }

    public function view(User $user, AuditEvent $auditEvent): bool
    {
        if (! $this->viewAny($user)) {
            return false;
        }

        if ($user->hasRole('super_admin_root')) {
            return true;
        }

        if ($auditEvent->cooperative_id === null) {
            return false;
        }

        if ($user->hasRole('super_admin')) {
            return $user->cooperatives()
                ->whereKey($auditEvent->cooperative_id)
                ->exists();
        }

        return $user->cooperative_id !== null
            && (string) $user->cooperative_id === (string) $auditEvent->cooperative_id;
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, AuditEvent $auditEvent): bool
    {
        return false;
    }

    public function delete(User $user, AuditEvent $auditEvent): bool
    {
        return false;
    }

    public function restore(User $user, AuditEvent $auditEvent): bool
    {
        return false;
    }

    public function forceDelete(User $user, AuditEvent $auditEvent): bool
    {
        return false;
    }
}
