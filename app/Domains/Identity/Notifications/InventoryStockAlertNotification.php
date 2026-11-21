<?php

declare(strict_types=1);

namespace App\Domains\Identity\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

final class InventoryStockAlertNotification extends Notification
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
            'alert_type' => $this->payload['alert_type'] ?? null,
            'stock_id' => $this->payload['stock_id'] ?? null,
            'cooperative_id' => $this->payload['cooperative_id'] ?? null,
            'available_quantity' => $this->payload['available_quantity'] ?? null,
            'threshold' => $this->payload['threshold'] ?? null,
            'episode_id' => $this->payload['episode_id'] ?? null,
            'occurred_at' => now()->toIso8601String(),
        ];
    }
}
