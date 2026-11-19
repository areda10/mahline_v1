<?php

declare(strict_types=1);

namespace App\Shared\Contracts;

interface ActorAuthorizer
{
    public function authorize(string $actorId, string $ability, mixed $subject = null): void;
}
