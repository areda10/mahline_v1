<?php

declare(strict_types=1);

namespace App\Domains\Orders\Notifications;

use App\Domains\Orders\Models\CooperativeOrder;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

final class OrderReadyForShipmentNotification extends Notification
{
    use Queueable;

    public function __construct(private readonly CooperativeOrder $cooperativeOrder) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'event' => 'order.ready_for_shipment',
            'title' => 'Votre commande est prête à être livrée',
            'message' => 'La coopérative a terminé la préparation de votre commande.',
            'order_id' => (string) $this->cooperativeOrder->order_id,
            'order_reference' => (string) $this->cooperativeOrder->order?->reference,
            'cooperative_order_id' => (string) $this->cooperativeOrder->getKey(),
            'cooperative_order_reference' => (string) $this->cooperativeOrder->reference,
            'cooperative_id' => (string) $this->cooperativeOrder->cooperative_id,
        ];
    }
}
