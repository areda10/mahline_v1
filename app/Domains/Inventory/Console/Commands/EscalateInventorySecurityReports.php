<?php

declare(strict_types=1);

namespace App\Domains\Inventory\Console\Commands;

use App\Domains\Inventory\Models\InventoryOutboxMessage;
use App\Domains\Inventory\Models\InventorySecurityReport;
use App\Domains\Inventory\Models\InventorySecurityReportEvent;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

final class EscalateInventorySecurityReports extends Command
{
    protected $signature = 'inventory:escalate-security-reports {--limit=100}';

    protected $description = 'Escalate urgent Inventory security reports after their two-hour response deadline.';

    public function handle(): int
    {
        $limit = max(1, min(1000, (int) $this->option('limit')));
        $ids = InventorySecurityReport::query()
            ->where('urgency', 'urgent')
            ->whereIn('status', ['open', 'under_review', 'reopened'])
            ->whereNotNull('response_due_at')
            ->where('response_due_at', '<=', now())
            ->whereNull('escalated_at')
            ->orderBy('response_due_at')
            ->limit($limit)
            ->pluck('id');

        $escalated = 0;
        foreach ($ids as $id) {
            DB::transaction(function () use ($id, &$escalated): void {
                $report = InventorySecurityReport::query()->whereKey($id)->lockForUpdate()->first();
                if ($report === null || $report->escalated_at !== null
                    || ! in_array($report->status, ['open', 'under_review', 'reopened'], true)
                    || $report->response_due_at === null
                    || $report->response_due_at->isFuture()) {
                    return;
                }

                $report->escalated_at = now();
                $report->save();

                InventorySecurityReportEvent::query()->create([
                    'report_id' => (string) $report->getKey(),
                    'event_type' => 'escalated',
                    'old_status' => (string) $report->status,
                    'new_status' => (string) $report->status,
                    'reason' => 'Délai de traitement urgent de deux heures dépassé. Le blocage de sécurité reste actif.',
                    'actor_id' => null,
                    'actor_type' => 'system',
                    'metadata' => ['response_due_at' => $report->response_due_at->toIso8601String()],
                    'occurred_at' => now(),
                ]);

                InventoryOutboxMessage::query()->create([
                    'event_key' => 'inventory.security_report.escalated',
                    'payload' => [
                        'report_id' => (string) $report->getKey(),
                        'cooperative_id' => (string) $report->cooperative_id,
                        'cooperative_order_id' => $report->cooperative_order_id,
                        'urgency' => $report->urgency,
                    ],
                    'available_at' => now(),
                ]);
                $escalated++;
            }, 3);
        }

        $this->info(sprintf('%d urgent security report(s) escalated.', $escalated));

        return self::SUCCESS;
    }
}
