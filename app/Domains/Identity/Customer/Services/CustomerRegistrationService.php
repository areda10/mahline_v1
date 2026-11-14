<?php

declare(strict_types=1);

namespace App\Domains\Identity\Customer\Services;

use App\Core\Foundation\Services\BaseService;
use App\Domains\Audit\Services\AuditService;
use App\Domains\Identity\Customer\Exceptions\CustomerVerificationDeliveryException;
use App\Domains\Identity\Customer\Models\CustomerEmailVerification;
use App\Domains\Identity\Customer\Notifications\CustomerEmailVerificationNotification;
use App\Domains\Identity\Authorization\Models\Role;
use App\Domains\Identity\Enums\UserStatus;
use App\Domains\Identity\Password\Rules\PasswordPolicy;
use App\Domains\Identity\Users\Models\User;
use App\Shared\DTOs\AuditEntryDTO;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Throwable;

final class CustomerRegistrationService extends BaseService
{
    private const VERIFICATION_EXPIRATION_HOURS = 24;

    private const RESEND_MAX_ATTEMPTS = 5;

    private const RESEND_WINDOW_SECONDS = 3600;

    public function __construct(
        private readonly AuditService $audit,
    ) {}

    /**
     * Register a pending customer. A duplicate address receives the same
     * public response as a new registration to avoid account enumeration.
     */
    public function register(array $attributes): bool
    {
        $email = mb_strtolower(trim((string) $attributes['email']));
        $password = (string) $attributes['password'];

        PasswordPolicy::validate($password, new User([
            'first_name' => (string) $attributes['first_name'],
            'last_name' => (string) $attributes['last_name'],
            'email' => $email,
        ]));

        if (User::withTrashed()->where('email', $email)->exists()) {
            return false;
        }

        $token = bin2hex(random_bytes(32));

        try {
            $user = DB::transaction(function () use ($attributes, $email, $password, $token): User {
                $role = Role::query()
                    ->where('slug', 'customer')
                    ->where('is_active', true)
                    ->first();

                if ($role === null) {
                    throw new RuntimeException('The customer role is not configured.');
                }

                $firstName = trim((string) $attributes['first_name']);
                $lastName = trim((string) $attributes['last_name']);

                $user = User::query()->create([
                    'first_name' => $firstName,
                    'last_name' => $lastName,
                    'display_name' => trim($firstName.' '.$lastName),
                    'email' => $email,
                    'telephone' => trim((string) $attributes['telephone']),
                    'password' => $password,
                    'status' => UserStatus::Pending,
                    'locale' => 'fr',
                    'timezone' => 'Africa/Casablanca',
                ]);

                $user->roles()->attach($role->getKey());

                $verification = CustomerEmailVerification::query()->create([
                    'user_id' => $user->getKey(),
                    'token_hash' => hash('sha256', $token),
                    'expires_at' => now()->addHours(self::VERIFICATION_EXPIRATION_HOURS),
                    'created_at' => now(),
                ]);

                $this->audit->record(new AuditEntryDTO(
                    eventKey: 'identity.customer.registration_created',
                    action: 'customer_registration_created',
                    subjectType: User::class,
                    subjectId: (string) $user->getKey(),
                    metadata: [
                        'status' => UserStatus::Pending->value,
                        'verification_id' => (string) $verification->getKey(),
                    ],
                ));

                return $user;
            });
        } catch (\Illuminate\Database\QueryException $exception) {
            if (User::withTrashed()->where('email', $email)->exists()) {
                return false;
            }

            throw $exception;
        }

        $this->deliverVerification($user, $token);

        return true;
    }

    /**
     * Verify the email and activate the customer atomically.
     */
    public function verifyEmail(string $token): bool
    {
        if ($token === '') {
            return false;
        }

        $tokenHash = hash('sha256', $token);

        $reference = CustomerEmailVerification::query()
            ->where('token_hash', $tokenHash)
            ->first();

        if ($reference === null) {
            return false;
        }

        $verifiedUser = DB::transaction(function () use ($reference, $tokenHash): ?User {
            $user = User::query()
                ->whereKey($reference->user_id)
                ->lockForUpdate()
                ->first();

            if ($user === null) {
                return null;
            }

            $verification = CustomerEmailVerification::query()
                ->whereKey($reference->getKey())
                ->where('user_id', $user->getKey())
                ->lockForUpdate()
                ->first();

            if (
                $verification === null
                || ! hash_equals((string) $verification->token_hash, $tokenHash)
                || $verification->used_at !== null
                || $verification->revoked_at !== null
                || now()->greaterThanOrEqualTo($verification->expires_at)
                || $user->status !== UserStatus::Pending
                || $user->email_verified_at !== null
                || ! $user->roles()->where('slug', 'customer')->exists()
            ) {
                return null;
            }

            $user->forceFill([
                'email_verified_at' => now(),
                'status' => UserStatus::Active,
            ])->save();

            $verification->used_at = now();
            $verification->save();

            CustomerEmailVerification::query()
                ->where('user_id', $user->getKey())
                ->where('id', '<>', $verification->getKey())
                ->whereNull('used_at')
                ->whereNull('revoked_at')
                ->update(['revoked_at' => now()]);

            $this->audit->record(new AuditEntryDTO(
                eventKey: 'identity.customer.email_verified',
                action: 'customer_email_verified',
                subjectType: User::class,
                subjectId: (string) $user->getKey(),
                metadata: [
                    'status' => UserStatus::Active->value,
                ],
            ));

            return $user;
        });

        return $verifiedUser !== null;
    }

    /**
     * Resend a verification link with five requests per hour, followed by
     * a one-hour block. Unknown and ineligible addresses receive no mail.
     */
    public function resendVerification(string $email): void
    {
        $email = mb_strtolower(trim($email));
        $key = 'customer-email-verification:'.hash('sha256', $email);
        $blockKey = $key.':blocked';

        if (Cache::has($blockKey)) {
            throw new TooManyRequestsHttpException(
                self::RESEND_WINDOW_SECONDS,
                'Too many verification requests.',
            );
        }

        if (RateLimiter::tooManyAttempts($key, self::RESEND_MAX_ATTEMPTS)) {
            Cache::put($blockKey, true, now()->addSeconds(self::RESEND_WINDOW_SECONDS));
            RateLimiter::clear($key);

            throw new TooManyRequestsHttpException(
                self::RESEND_WINDOW_SECONDS,
                'Too many verification requests.',
            );
        }

        RateLimiter::hit($key, self::RESEND_WINDOW_SECONDS);

        $user = User::query()
            ->where('email', $email)
            ->where('status', UserStatus::Pending->value)
            ->whereNull('email_verified_at')
            ->first();

        if (
            $user === null
            || ! $user->roles()->where('slug', 'customer')->exists()
        ) {
            return;
        }

        $token = bin2hex(random_bytes(32));

        $issued = DB::transaction(function () use ($user, $token): bool {
            $lockedUser = User::query()
                ->whereKey($user->getKey())
                ->lockForUpdate()
                ->first();

            if (
                $lockedUser === null
                || $lockedUser->status !== UserStatus::Pending
                || $lockedUser->email_verified_at !== null
                || ! $lockedUser->roles()->where('slug', 'customer')->exists()
            ) {
                return false;
            }

            CustomerEmailVerification::query()
                ->where('user_id', $lockedUser->getKey())
                ->whereNull('used_at')
                ->whereNull('revoked_at')
                ->update(['revoked_at' => now()]);

            CustomerEmailVerification::query()->create([
                'user_id' => $lockedUser->getKey(),
                'token_hash' => hash('sha256', $token),
                'expires_at' => now()->addHours(self::VERIFICATION_EXPIRATION_HOURS),
                'created_at' => now(),
            ]);

            return true;
        });

        if ($issued) {
            $this->deliverVerification($user, $token);
        }
    }

    private function deliverVerification(User $user, string $token): void
    {
        $expiresAt = CustomerEmailVerification::query()
            ->where('token_hash', hash('sha256', $token))
            ->value('expires_at');

        $verificationUrl = route('customer.verification.verify', [
            'token' => $token,
        ]);

        try {
            Notification::route('mail', $user->email)->notify(
                new CustomerEmailVerificationNotification($verificationUrl),
            );
        } catch (Throwable $exception) {
            CustomerEmailVerification::query()
                ->where('user_id', $user->getKey())
                ->where('token_hash', hash('sha256', $token))
                ->whereNull('used_at')
                ->whereNull('revoked_at')
                ->update(['revoked_at' => now()]);

            throw new CustomerVerificationDeliveryException(
                'The customer verification email could not be delivered.',
                previous: $exception,
            );
        }

        $this->audit->record(new AuditEntryDTO(
            eventKey: 'identity.customer.verification_email_sent',
            action: 'customer_verification_email_sent',
            subjectType: User::class,
            subjectId: (string) $user->getKey(),
            metadata: [
                'expires_at' => $expiresAt,
            ],
        ));
    }
}
