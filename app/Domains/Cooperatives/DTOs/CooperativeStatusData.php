<?php

declare(strict_types=1);

namespace App\Domains\Cooperatives\DTOs;

final readonly class CooperativeStatusData
{
    public function __construct(
        public string $cooperativeId,
        public string $status,
        public bool $isActive,
        public bool $isDeleted,
    ) {}
}
