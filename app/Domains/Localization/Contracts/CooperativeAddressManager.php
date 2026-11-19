<?php

declare(strict_types=1);

namespace App\Domains\Localization\Contracts;

interface CooperativeAddressManager
{
    /** @param array<string, mixed> $attributes */
    public function createAddressForCooperative(string $actorId, string $cooperativeId, array $attributes): void;

    /** @param array<string, mixed> $attributes */
    public function updateAddressForCooperative(string $actorId, string $cooperativeId, array $attributes): void;

    public function softDeleteCooperativeAddressesById(string $cooperativeId, string $actorId): string;

    public function restoreLatestCooperativeAddressesById(string $cooperativeId, string $actorId): void;
}
