<?php

declare(strict_types=1);

namespace App\Domains\Identity\Users\Observers;

use App\Domains\Identity\Users\Models\User;
use DomainException;

final class UserObserver
{
    /**
     * A Root account is created active and its lifecycle status is immutable.
     *
     * Password and email changes remain outside this status guard.
     */
    public function updating(User $user): void
    {
        if (
            $user->isDirty('status')
            && $user->roles()->where('slug', 'super_admin_root')->exists()
        ) {
            throw new DomainException('The super admin root account status cannot be changed.');
        }
    }

    /**
     * The platform Root account is a permanent bootstrap identity.
     *
     * The deleting event runs for both soft deletes and force deletes.
     */
    public function deleting(User $user): void
    {
        if ($user->roles()->where('slug', 'super_admin_root')->exists()) {
            throw new DomainException('The super admin root account cannot be deleted.');
        }
    }
}
