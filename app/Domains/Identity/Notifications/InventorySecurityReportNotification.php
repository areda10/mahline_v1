<?php

declare(strict_types=1);

namespace App\Domains\Identity\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

final class InventorySecurityReportNotification extends Notification
{
    use Queueable;

    /** @param array<string, mixed> $payload */
    public function __construct(
        private readonly string $eventKey,
        private readonly array $payload,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /** @return array<string, mixed> */
    public function toDatabase(object $notifiable): array
    {
        return [
            'domain' => 'inventory',
            'event' => $this->eventKey,
            'title' => match ($this->eventKey) {
                'inventory.security_report.created' => 'Nouveau signalement de sécurité',
                'inventory.security_report.escalated' => 'Signalement de sécurité escaladé',
                'inventory.security_report.closed' => 'Signalement de sécurité clôturé',
                'inventory.security_report.reopened' => 'Signalement de sécurité réouvert',
                default => 'Mise à jour d’un signalement de sécurité',
            },
            'message' => 'Un signalement de sécurité concernant votre coopérative a été mis à jour.',
            'url' => route('inventory.security-reports.index'),
            'report_id' => $this->payload['report_id'] ?? null,
            'cooperative_id' => $this->payload['cooperative_id'] ?? null,
            'cooperative_order_id' => $this->payload['cooperative_order_id'] ?? null,
            'urgency' => $this->payload['urgency'] ?? null,
            'status' => $this->payload['status'] ?? null,
            'occurred_at' => now()->toIso8601String(),
        ];
    }
}
