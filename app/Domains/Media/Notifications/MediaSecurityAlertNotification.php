<?php

declare(strict_types=1);

namespace App\Domains\Media\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

final class MediaSecurityAlertNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly string $event,
        private readonly string $actorHash,
        private readonly string $ipHash,
        private readonly int $attempts,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('MAHLINE — alerte de sécurité MEDIA')
            ->greeting('Alerte de sécurité MEDIA')
            ->line('Un comportement d’accès inhabituel a été détecté.')
            ->line('Événement : '.$this->event)
            ->line('Identifiant acteur (empreinte) : '.$this->actorHash)
            ->line('Adresse IP (empreinte) : '.$this->ipHash)
            ->line('Tentatives sur la fenêtre : '.$this->attempts)
            ->action('Consulter les incidents MEDIA', route('media.incidents.index'));
    }
}
