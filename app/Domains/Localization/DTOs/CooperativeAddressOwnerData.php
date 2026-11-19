<?php

declare(strict_types=1);

namespace App\Domains\Localization\DTOs;

final readonly class CooperativeAddressOwnerData
{
    public function __construct(public string $id) {}
}
