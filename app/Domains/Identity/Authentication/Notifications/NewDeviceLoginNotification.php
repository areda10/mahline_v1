<?php

declare(strict_types=1);

namespace App\Domains\Identity\Authentication\Notifications;

use App\Domains\Identity\Authentication\Models\AuthenticationSession;
use App\Domains\Identity\Users\Models\User;
use DateTimeZone;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Carbon;

final class NewDeviceLoginNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly AuthenticationSession $session,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        /** @var User $notifiable */
        $locale = $this->resolveLocale($notifiable->locale);
        $timezone = $this->timezone($notifiable->timezone);
        $authenticatedAt = Carbon::parse($this->session->authenticated_at)
            ->setTimezone($timezone)
            ->locale($locale)
            ->translatedFormat('d F Y H:i');

        $messages = match ($locale) {
            'en' => [
                'subject' => 'New device sign-in to your MAHLINE account',
                'greeting' => 'Hello,',
                'intro' => 'A sign-in to your MAHLINE account was detected from a new device.',
                'date' => 'Date and time',
                'browser' => 'Browser',
                'device' => 'Device',
                'location' => 'Approximate location: unavailable',
                'ignore' => 'If this was you, no action is needed. If you do not recognize this activity, secure your account and contact your MAHLINE administrator.',
                'unknown' => 'Not available',
            ],
            'ar' => [
                'subject' => 'تسجيل دخول جديد إلى حسابك في MAHLINE',
                'greeting' => 'مرحباً،',
                'intro' => 'تم رصد تسجيل دخول إلى حسابك في MAHLINE من جهاز جديد.',
                'date' => 'التاريخ والوقت',
                'browser' => 'المتصفح',
                'device' => 'الجهاز',
                'location' => 'الموقع التقريبي: غير متاح',
                'ignore' => 'إذا كنت أنت من قام بهذا الدخول فلا يلزم اتخاذ أي إجراء. إذا لم تتعرف على هذا النشاط، يرجى تأمين حسابك والتواصل مع مسؤول MAHLINE.',
                'unknown' => 'غير متاح',
            ],
            default => [
                'subject' => 'Nouvelle connexion à votre compte MAHLINE',
                'greeting' => 'Bonjour,',
                'intro' => 'Une connexion à votre compte MAHLINE depuis un nouvel appareil a été détectée.',
                'date' => 'Date et heure',
                'browser' => 'Navigateur',
                'device' => 'Appareil',
                'location' => 'Localisation approximative : indisponible',
                'ignore' => 'Si vous êtes à l’origine de cette connexion, aucune action n’est nécessaire. Si vous ne reconnaissez pas cette activité, sécurisez votre compte et contactez votre administrateur MAHLINE.',
                'unknown' => 'Non disponible',
            ],
        };

        return (new MailMessage)
            ->subject($messages['subject'])
            ->greeting($messages['greeting'])
            ->line($messages['intro'])
            ->line($messages['date'].': '.$authenticatedAt)
            ->line($messages['browser'].': '.($this->session->browser ?: $messages['unknown']))
            ->line($messages['device'].': '.($this->session->device ?: $messages['unknown']))
            ->line($messages['location'])
            ->line($messages['ignore']);
    }

    private function resolveLocale(?string $locale): string
    {
        $locale = strtolower(substr((string) $locale, 0, 2));

        return in_array($locale, ['fr', 'en', 'ar'], true)
            ? $locale
            : 'fr';
    }

    private function timezone(?string $timezone): string
    {
        if (! $timezone) {
            return (string) config('app.timezone', 'UTC');
        }

        try {
            new DateTimeZone($timezone);

            return $timezone;
        } catch (\Throwable) {
            return (string) config('app.timezone', 'UTC');
        }
    }
}
