<?php

declare(strict_types=1);

namespace App\Domains\Orders\Notifications;

use App\Domains\Orders\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

final class CustomerOrderLifecycleNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public bool $afterCommit = true;

    public function __construct(
        private readonly Order $order,
        private readonly string $event,
        private readonly string $title,
        private readonly string $message,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'event' => $this->event,
            'title' => $this->title,
            'message' => $this->message,
            'order_id' => (string) $this->order->getKey(),
            'order_reference' => (string) $this->order->reference,
            'url' => route('customer.orders.show', $this->order->getKey()),
            'priority' => in_array($this->event, ['order.created', 'order.confirmed'], true) ? 'high' : 'medium',
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject($this->title.' — MAHLINE')
            ->greeting('Bonjour,')
            ->line($this->message)
            ->line('Référence de commande : '.$this->order->reference)
            ->action('Consulter ma commande', route('customer.orders.show', $this->order->getKey()))
            ->salutation('L’équipe MAHLINE');
    }
}
