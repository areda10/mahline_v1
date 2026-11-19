<?php

declare(strict_types=1);

namespace App\Domains\Identity\Authorization\Services;

use App\Domains\Identity\Users\Models\User;
use App\Shared\Contracts\ActorAuthorizer;
use Illuminate\Support\Facades\Gate;

final class IdentityActorAuthorizer implements ActorAuthorizer
{
    public function authorize(string $actorId, string $ability, mixed $subject = null): void
    {
        $actor = User::query()->findOrFail($actorId);

        Gate::forUser($actor)->authorize($ability, $subject);
    }
}
