<?php

declare(strict_types=1);

namespace App\Shared\Contracts;

use App\Shared\DTOs\ActorContext;

interface ActorContextProvider
{
    public function forUserId(string $userId): ActorContext;
}
