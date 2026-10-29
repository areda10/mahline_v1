<?php

declare(strict_types=1);

namespace App\Shared\DTOs;

use DateTimeInterface;

final readonly class AuditEntryDTO
{
    public function __construct(
        public string $eventKey,
        public string $action,
        public ?string $actorType = null,
        public ?string $actorId = null,
        public ?string $cooperativeId = null,
        public ?string $subjectType = null,
        public ?string $subjectId = null,
        public ?array $oldValues = null,
        public ?array $newValues = null,
        public ?array $metadata = null,
        public ?string $ipAddress = null,
        public ?string $userAgent = null,
        public ?string $correlationId = null,
        public ?DateTimeInterface $occurredAt = null,
    ) {
    }
}
