<?php

declare(strict_types=1);

namespace App\Domains\Orders\Notifications;

use App\Domains\Orders\Models\CooperativeOrder;
use Illuminate\Notifications\Notification;

final class CooperativeOrderWorkflowNotification extends Notification
{
    public function __construct(
        private readonly CooperativeOrder $cooperativeOrder,
        private readonly string $event,
        private readonly string $title,
        private readonly string $message,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'event' => $this->event,
            'title' => $this->title,
            'message' => $this->message,
            'order_id' => (string) $this->cooperativeOrder->order_id,
            'order_reference' => (string) $this->cooperativeOrder->order?->reference,
            'cooperative_order_id' => (string) $this->cooperativeOrder->getKey(),
            'cooperative_order_reference' => (string) $this->cooperativeOrder->reference,
            'cooperative_id' => (string) $this->cooperativeOrder->cooperative_id,
        ];
    }
}
