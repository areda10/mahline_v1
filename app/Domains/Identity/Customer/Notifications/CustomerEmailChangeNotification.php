<?php

declare(strict_types=1);

namespace App\Domains\Identity\Customer\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

final class CustomerEmailChangeNotification extends Notification
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
            ->subject('Confirmation du changement d’adresse e-mail — MAHLINE')
            ->greeting('Bonjour,')
            ->line('Une demande de changement d’adresse e-mail a été effectuée pour votre compte MAHLINE.')
            ->line('Confirmez la nouvelle adresse en utilisant le bouton ci-dessous.')
            ->action('Confirmer ma nouvelle adresse', $this->verificationUrl)
            ->line('Ce lien expire dans 30 minutes et ne peut être utilisé qu’une seule fois.')
            ->line('Si vous n’êtes pas à l’origine de cette demande, ignorez ce message. Votre adresse actuelle restera inchangée.');
    }
}