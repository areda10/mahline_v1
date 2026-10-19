<?php

declare(strict_types=1);

namespace App\Domains\Identity\Password\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

final class PasswordResetLinkNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly string $resetUrl,
    ) {
    }

    /**
     * Envoi direct : aucun worker de queue n'est requis.
     *
     * Le transport mail reste configurable dans Laravel.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage())
            ->subject('Réinitialisation de votre mot de passe MAHLINE')
            ->greeting('Bonjour,')
            ->line(
                'Nous avons reçu une demande de réinitialisation de votre mot de passe MAHLINE.'
            )
            ->action(
                'Réinitialiser mon mot de passe',
                $this->resetUrl,
            )
            ->line(
                'Ce lien est valable pendant 60 minutes.'
            )
            ->line(
                'Si vous n’êtes pas à l’origine de cette demande, ignorez ce courriel.'
            );
    }
}