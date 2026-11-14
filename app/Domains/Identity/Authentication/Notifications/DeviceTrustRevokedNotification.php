<?php

declare(strict_types=1);

namespace App\Domains\Identity\Authentication\Notifications;

use App\Domains\Identity\Users\Models\User;
use DateTimeZone;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Carbon;

final class DeviceTrustRevokedNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly string $device,
        private readonly ?string $browser,
        private readonly ?string $ipAddress,
        private readonly Carbon $revokedAt,
    ) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        /** @var User $notifiable */
        $locale = $this->locale($notifiable->locale);
        $date = $this->revokedAt->copy()
            ->setTimezone($this->timezone($notifiable->timezone))
            ->locale($locale)
            ->translatedFormat('d F Y H:i');

        $messages = match ($locale) {
            'en' => [
                'subject' => 'A device was revoked from your MAHLINE account',
                'greeting' => 'Hello,',
                'intro' => 'A device was successfully revoked from your MAHLINE account.',
                'device' => 'Device',
                'browser' => 'Browser',
                'date' => 'Revocation date and time',
                'ip' => 'IP address',
                'footer' => 'All active web sessions belonging to this device were revoked. Its device proof can no longer be used to sign in to this account. Your other devices and API tokens were not changed.',
                'unknown' => 'Not available',
            ],
            'ar' => [
                'subject' => 'تم إلغاء الثقة بجهاز من حساب MAHLINE الخاص بك',
                'greeting' => 'مرحباً،',
                'intro' => 'تم إلغاء الثقة بالجهاز بنجاح من حساب MAHLINE الخاص بك.',
                'device' => 'الجهاز',
                'browser' => 'المتصفح',
                'date' => 'تاريخ ووقت الإلغاء',
                'ip' => 'عنوان IP',
                'footer' => 'تم إلغاء جميع جلسات الويب النشطة المرتبطة بهذا الجهاز. لم يتم تغيير أجهزتك الأخرى أو مفاتيح API.',
                'unknown' => 'غير متاح',
            ],
            default => [
                'subject' => 'Un appareil a été révoqué de votre compte MAHLINE',
                'greeting' => 'Bonjour,',
                'intro' => 'La révocation d’un appareil de votre compte MAHLINE a été effectuée.',
                'device' => 'Appareil',
                'browser' => 'Navigateur',
                'date' => 'Date et heure de révocation',
                'ip' => 'Adresse IP',
                'footer' => 'Toutes les sessions Web actives de cet appareil ont été révoquées. Son justificatif ne peut plus être utilisé pour se connecter à ce compte. Vos autres appareils et vos clés API n’ont pas été modifiés.',
                'unknown' => 'Non disponible',
            ],
        };

        return (new MailMessage)
            ->subject($messages['subject'])
            ->greeting($messages['greeting'])
            ->line($messages['intro'])
            ->line($messages['device'].': '.$this->device)
            ->line($messages['browser'].': '.($this->browser ?: $messages['unknown']))
            ->line($messages['ip'].': '.($this->ipAddress ?: $messages['unknown']))
            ->line($messages['date'].': '.$date)
            ->line($messages['footer']);
    }

    /** @return array<string, string> */
    public function toDatabase(object $notifiable): array
    {
        return [
            'event' => 'device_trust_revoked',
            'device' => $this->device,
            'browser' => $this->browser,
            'ip_address' => $this->ipAddress,
            'revoked_at' => $this->revokedAt->toIso8601String(),
        ];
    }

    private function locale(?string $locale): string
    {
        $locale = strtolower(substr((string) $locale, 0, 2));

        return in_array($locale, ['fr', 'en', 'ar'], true) ? $locale : 'fr';
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
