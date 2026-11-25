<?php

declare(strict_types=1);

namespace App\Domains\Orders\Notifications;

use App\Domains\Orders\Models\CooperativeOrder;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

final class CustomerOrderCancelledNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(private readonly CooperativeOrder $cooperativeOrder) {}

    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'event' => 'order.customer_cancelled',
            'title' => 'Sous-commande annulée',
            'message' => 'Votre sous-commande '.$this->cooperativeOrder->reference.' a été annulée.',
            'order_id' => (string) $this->cooperativeOrder->order_id,
            'order_reference' => (string) $this->cooperativeOrder->order?->reference,
            'cooperative_order_id' => (string) $this->cooperativeOrder->getKey(),
            'cooperative_order_reference' => (string) $this->cooperativeOrder->reference,
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Annulation de votre sous-commande MAHLINE')
            ->greeting('Bonjour,')
            ->line('Votre sous-commande '.$this->cooperativeOrder->reference.' a été annulée à votre demande.')
            ->line('Commande : '.$this->cooperativeOrder->order?->reference)
            ->line('Le total de votre commande a été recalculé.')
            ->action('Consulter ma commande', route('customer.orders.show', $this->cooperativeOrder->order_id))
            ->salutation('L’équipe MAHLINE');
    }
}
