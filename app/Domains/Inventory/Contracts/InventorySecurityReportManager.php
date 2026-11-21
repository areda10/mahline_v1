<?php

declare(strict_types=1);

namespace App\Domains\Inventory\Contracts;

use App\Domains\Inventory\Enums\InventorySecurityReportUrgency;
use App\Domains\Inventory\Models\InventorySecurityReport;

interface InventorySecurityReportManager
{
    public function reportRisk(
        string $cooperativeId,
        ?string $cooperativeOrderId,
        string $category,
        string $description,
        InventorySecurityReportUrgency $urgency,
        string $actorId,
    ): string;

    public function visibleReport(string $reportId, string $actorId): InventorySecurityReport;

    public function close(string $reportId, string $reason, string $actorId): void;

    public function reopen(string $reportId, string $reason, string $actorId): void;
}
