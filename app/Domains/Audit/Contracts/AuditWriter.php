<?php

declare(strict_types=1);

namespace App\Domains\Audit\Contracts;

use App\Domains\Audit\Models\AuditEvent;
use App\Shared\DTOs\AuditEntryDTO;

interface AuditWriter
{
    public function record(AuditEntryDTO $entry): AuditEvent;

    public function verifyIntegrity(): bool;
}
