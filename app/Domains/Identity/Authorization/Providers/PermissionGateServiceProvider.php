<?php

declare(strict_types=1);

namespace App\Domains\Identity\Authorization\Providers;

use App\Domains\Identity\Users\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

final class PermissionGateServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Gate::before(
            static function (
                mixed $user,
                string $ability,
                array $arguments,
            ): ?bool {
                if (! $user instanceof User) {
                    return null;
                }

                // Toute Policy reçoit une ressource ou une classe de modèle.
                // Le fallback générique ne doit jamais court-circuiter ces autorisations.
                if ($arguments !== []) {
                    return null;
                }

                // Les permissions génériques MAHLINE utilisent domaine.action.
                if (preg_match('/^[a-z][a-z0-9_]*(?:\.[a-z][a-z0-9_]*)+$/D', $ability) !== 1) {
                    return null;
                }

                return $user->hasPermission($ability)
                    ? true
                    : null;
            },
        );
    }
}
