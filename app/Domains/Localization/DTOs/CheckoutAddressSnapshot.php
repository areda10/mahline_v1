<?php

declare(strict_types=1);

namespace App\Domains\Localization\DTOs;

final readonly class CheckoutAddressSnapshot
{
    /** @param array<string, mixed> $attributes */
    public function __construct(public array $attributes) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return $this->attributes;
    }
}
