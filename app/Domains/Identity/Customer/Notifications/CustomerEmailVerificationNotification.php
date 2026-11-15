<?php

declare(strict_types=1);

namespace App\Domains\Identity\Customer\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

final class CustomerEmailVerificationNotification extends Notification
{
    public function __construct(
        private readonly string $verificationUrl,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Vérifiez votre adresse e-mail — MAHLINE')
            ->greeting('Bienvenue sur MAHLINE,')
            ->line('Pour activer votre espace client, veuillez confirmer votre adresse e-mail.')
            ->action('Vérifier mon adresse e-mail', $this->verificationUrl)
            ->line('Ce lien est valable pendant 24 heures et ne peut être utilisé qu’une seule fois.')
            ->line('Si vous n’êtes pas à l’origine de cette inscription, ignorez ce message.');
    }
}
