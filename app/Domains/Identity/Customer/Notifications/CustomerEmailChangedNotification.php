<?php

declare(strict_types=1);

namespace App\Domains\Identity\Customer\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

final class CustomerEmailChangedNotification extends Notification
{
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Adresse e-mail MAHLINE modifiée')
            ->greeting('Bonjour,')
            ->line('L’adresse e-mail associée à votre compte MAHLINE vient d’être modifiée après vérification de la nouvelle adresse.')
            ->line('Toutes les sessions et tous les jetons API de ce compte ont été révoqués.')
            ->line('Si vous n’êtes pas à l’origine de cette modification, contactez immédiatement le support MAHLINE.');
    }
}