<?php

declare(strict_types=1);

namespace App\Domains\Identity\UserManagement\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

final class UserInvitationNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly string $invitationUrl,
    ) {}

    /**
     * Envoi direct : aucun worker de queue n'est requis.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Invitation à rejoindre MAHLINE')
            ->greeting('Bonjour,')
            ->line(
                'Un compte utilisateur MAHLINE a été créé pour vous.'
            )
            ->line(
                'Pour activer votre compte, veuillez définir votre propre mot de passe.'
            )
            ->action(
                'Définir mon mot de passe',
                $this->invitationUrl,
            )
            ->line(
                'Ce lien est valable pendant 60 minutes et ne peut être utilisé qu’une seule fois.'
            )
            ->line(
                'Si vous n’attendiez pas cette invitation, vous pouvez ignorer ce courriel.'
            );
    }
}
