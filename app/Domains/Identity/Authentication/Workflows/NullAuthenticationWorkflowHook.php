<?php

declare(strict_types=1);

namespace App\Domains\Identity\Authentication\Workflows;

use App\Domains\Identity\Authentication\Models\AuthenticationSession;
use App\Domains\Identity\Users\Models\User;

final class NullAuthenticationWorkflowHook implements AuthenticationWorkflowHook
{
    public function afterDeviceRemembered(
        User $user,
        AuthenticationSession $session,
    ): void {
        // Intentionally empty.
    }
}