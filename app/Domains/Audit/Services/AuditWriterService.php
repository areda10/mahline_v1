<?php

declare(strict_types=1);

namespace App\Domains\Audit\Services;

use App\Domains\Audit\Contracts\AuditWriter;
use App\Shared\DTOs\AuditEntryDTO;

final class AuditWriterService implements AuditWriter
{
    public function __construct(
        private readonly AuditService $audit,
    ) {}

    public function record(AuditEntryDTO $entry): void
    {
        $this->audit->record($entry);
    }

    public function verifyIntegrity(): bool
    {
        return $this->audit->verifyIntegrity();
    }
}
