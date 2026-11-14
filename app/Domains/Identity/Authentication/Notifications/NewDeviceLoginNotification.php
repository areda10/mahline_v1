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
                'ip' => 'IP address',
                'browser' => 'Browser',
                'device' => 'Device',
                'location' => 'Approximate location: unavailable',
                'ignore' => 'If this was you, no action is needed. If you do not recognize this activity, open Security > Devices in MAHLINE and revoke this device.',
                'pending' => 'This device can access your account now, but it will become trusted after 24 hours unless you revoke it.',
                'pending_limit' => 'This device can access your account now. If all five trusted-device slots are still occupied after 24 hours, it will remain pending until you replace one in Security > Devices.',
                'trusted' => 'This device is now trusted for future sign-ins.',
                'unknown' => 'Not available',
            ],
            'ar' => [
                'subject' => 'تسجيل دخول جديد إلى حسابك في MAHLINE',
                'greeting' => 'مرحباً،',
                'intro' => 'تم رصد تسجيل دخول إلى حسابك في MAHLINE من جهاز جديد.',
                'date' => 'التاريخ والوقت',
                'ip' => 'عنوان IP',
                'browser' => 'المتصفح',
                'device' => 'الجهاز',
                'location' => 'الموقع التقريبي: غير متاح',
                'ignore' => 'إذا كنت أنت من قام بهذا الدخول فلا يلزم اتخاذ أي إجراء. إذا لم تتعرف على هذا النشاط، يرجى تأمين حسابك والتواصل مع مسؤول MAHLINE.',
                'pending' => 'يمكن لهذا الجهاز استخدام حسابك الآن، وسيصبح موثوقاً بعد 24 ساعة ما لم تقم بإلغائه.',
                'pending_limit' => 'يمكن لهذا الجهاز استخدام حسابك الآن. إذا ظلت أماكن الأجهزة الموثوقة الخمسة مشغولة بعد 24 ساعة، فسيبقى قيد الانتظار حتى تستبدل جهازاً من قسم الأمان > الأجهزة.',
                'trusted' => 'هذا الجهاز موثوق الآن لتسجيلات الدخول المستقبلية.',
                'unknown' => 'غير متاح',
            ],
            default => [
                'subject' => 'Nouvelle connexion à votre compte MAHLINE',
                'greeting' => 'Bonjour,',
                'intro' => 'Une connexion à votre compte MAHLINE depuis un nouvel appareil a été détectée.',
                'date' => 'Date et heure',
                'ip' => 'Adresse IP',
                'browser' => 'Navigateur',
                'device' => 'Appareil',
                'location' => 'Localisation approximative : indisponible',
                'ignore' => 'Si vous êtes à l’origine de cette connexion, aucune action n’est nécessaire. Sinon, ouvrez Sécurité > Appareils dans MAHLINE et révoquez cet appareil.',
                'pending' => 'Cet appareil peut utiliser votre compte dès maintenant. Il deviendra de confiance après 24 heures si vous ne le révoquez pas.',
                'pending_limit' => 'Cet appareil peut utiliser votre compte dès maintenant. Si les cinq places sont toujours occupées après 24 heures, il restera en attente jusqu’au remplacement d’un appareil dans Sécurité > Appareils.',
                'trusted' => 'Cet appareil est maintenant reconnu comme appareil de confiance.',
                'unknown' => 'Non disponible',
            ],
        };

        $knownDevice = $this->session->knownDevice;
        $deviceStatus = $knownDevice?->status?->value;
        $trustedCount = $knownDevice === null
            ? 0
            : \App\Domains\Identity\Authentication\Models\KnownDevice::query()
                ->where('user_id', $this->session->user_id)
                ->where('status', 'trusted')
                ->count();

        $mail = (new MailMessage)
            ->subject($messages['subject'])
            ->greeting($messages['greeting'])
            ->line($messages['intro']);

        if ($deviceStatus === 'pending') {
            $mail->line($trustedCount >= 5 ? $messages['pending_limit'] : $messages['pending']);
        } elseif ($deviceStatus === 'trusted') {
            $mail->line($messages['trusted']);
        }

        return $mail
            ->line($messages['date'].': '.$authenticatedAt)
            ->line($messages['ip'].': '.($this->session->ip_address ?: $messages['unknown']))
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
