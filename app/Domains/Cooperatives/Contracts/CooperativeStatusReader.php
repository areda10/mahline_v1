<?php

declare(strict_types=1);

namespace App\Domains\Cooperatives\Contracts;

use App\Domains\Cooperatives\DTOs\CooperativeStatusData;

interface CooperativeStatusReader
{
    public function findStatus(string $cooperativeId): ?CooperativeStatusData;
}
