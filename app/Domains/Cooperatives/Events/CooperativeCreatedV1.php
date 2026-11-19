<?php

declare(strict_types=1);

namespace App\Domains\Cooperatives\Events;

final readonly class CooperativeCreatedV1
{
    public function __construct(
        public string $cooperativeId,
        public string $creatorUserId,
    ) {}
}
