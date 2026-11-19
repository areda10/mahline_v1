<?php

declare(strict_types=1);

namespace App\Domains\Audit\Contracts;

use App\Shared\DTOs\AuditEntryDTO;

interface AuditWriter
{
    public function record(AuditEntryDTO $entry): void;

    public function verifyIntegrity(): bool;
}
