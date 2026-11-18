<?php

declare(strict_types=1);

namespace App\Domains\Media\Notifications;

use App\Domains\Media\Models\MediaIncident;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

final class MediaIncidentNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly MediaIncident $incident,
        private readonly string $event,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('MAHLINE — incident MEDIA '.$this->incident->reference)
            ->greeting('Notification de sécurité MEDIA')
            ->line('Incident : '.$this->incident->reference)
            ->line('Objet : '.$this->incident->title)
            ->line('Événement : '.$this->event)
            ->line('Statut : '.$this->incident->status->value)
            ->action('Consulter l’incident', route('media.incidents.show', $this->incident));
    }
}
