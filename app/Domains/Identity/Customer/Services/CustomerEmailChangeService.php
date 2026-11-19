<?php

declare(strict_types=1);

namespace App\Domains\Identity\Customer\Services;

use App\Core\Foundation\Services\BaseService;
use App\Domains\Audit\Contracts\AuditWriter;
use App\Domains\Identity\Authentication\Services\SessionService;
use App\Domains\Identity\Customer\Models\CustomerEmailChangeRequest;
use App\Domains\Identity\Customer\Notifications\CustomerEmailChangedNotification;
use App\Domains\Identity\Customer\Notifications\CustomerEmailChangeNotification;
use App\Domains\Identity\Enums\UserStatus;
use App\Domains\Identity\Users\Models\User;
use App\Shared\DTOs\AuditEntryDTO;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

final class CustomerEmailChangeService extends BaseService
{
    private const EXPIRATION_MINUTES = 30;

    public function __construct(
        private readonly AuditWriter $audit,
        private readonly SessionService $sessions,
    ) {}

    /**
     * Start a password-confirmed email change. The current address remains
     * primary until the new address has been verified.
     */
    public function requestChange(User $customer, string $newEmail, string $password): void
    {
        $newEmail = mb_strtolower(trim($newEmail));

        $token = bin2hex(random_bytes(32));

        $request = DB::transaction(function () use ($customer, $newEmail, $password, $token): CustomerEmailChangeRequest {
            $lockedCustomer = User::query()
                ->whereKey($customer->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $this->assertEligibleCustomer($lockedCustomer);

            if (! Hash::check($password, (string) $lockedCustomer->password)) {
                throw ValidationException::withMessages([
                    'current_password' => 'Le mot de passe actuel est incorrect.',
                ]);
            }

            if ($newEmail === mb_strtolower((string) $lockedCustomer->email)) {
                throw ValidationException::withMessages([
                    'new_email' => 'Cette adresse e-mail est déjà associée à votre compte.',
                ]);
            }

            if (User::withTrashed()->where('email', $newEmail)->exists()) {
                throw ValidationException::withMessages([
                    'new_email' => 'Cette adresse e-mail est déjà utilisée.',
                ]);
            }

            CustomerEmailChangeRequest::query()
                ->where('user_id', $lockedCustomer->getKey())
                ->whereNull('used_at')
                ->whereNull('revoked_at')
                ->update(['revoked_at' => now()]);

            $emailChange = CustomerEmailChangeRequest::query()->create([
                'user_id' => $lockedCustomer->getKey(),
                'new_email' => $newEmail,
                'token_hash' => hash('sha256', $token),
                'expires_at' => now()->addMinutes(self::EXPIRATION_MINUTES),
                'created_at' => now(),
            ]);

            $this->audit->record(new AuditEntryDTO(
                eventKey: 'identity.customer.email_change_requested',
                action: 'customer_email_change_requested',
                actorType: User::class,
                actorId: (string) $lockedCustomer->getKey(),
                subjectType: User::class,
                subjectId: (string) $lockedCustomer->getKey(),
                metadata: [
                    'request_id' => (string) $emailChange->getKey(),
                    'expires_at' => $emailChange->expires_at->toISOString(),
                ],
            ));

            return $emailChange;
        });

        $verificationUrl = route('customer.email.change.verify', ['token' => $token]);

        try {
            Notification::route('mail', $request->new_email)->notify(
                new CustomerEmailChangeNotification($verificationUrl),
            );
        } catch (Throwable $exception) {
            CustomerEmailChangeRequest::query()
                ->whereKey($request->getKey())
                ->whereNull('used_at')
                ->whereNull('revoked_at')
                ->update(['revoked_at' => now()]);

            throw new RuntimeException(
                'The customer email change verification message could not be delivered.',
                previous: $exception,
            );
        }
    }

    /**
     * Confirm a one-time token, update the primary address, revoke all access,
     * then notify the previous address.
     */
    public function verifyChange(string $token): bool
    {
        if ($token === '') {
            return false;
        }

        $tokenHash = hash('sha256', $token);

        $reference = CustomerEmailChangeRequest::query()
            ->where('token_hash', $tokenHash)
            ->first();

        if ($reference === null) {
            return false;
        }

        $result = DB::transaction(function () use ($reference, $tokenHash): ?array {
            $customer = User::query()
                ->whereKey($reference->user_id)
                ->lockForUpdate()
                ->first();

            if ($customer === null) {
                return null;
            }

            $request = CustomerEmailChangeRequest::query()
                ->whereKey($reference->getKey())
                ->where('user_id', $customer->getKey())
                ->lockForUpdate()
                ->first();

            if (
                $request === null
                || ! hash_equals((string) $request->token_hash, $tokenHash)
                || $request->used_at !== null
                || $request->revoked_at !== null
                || now()->greaterThanOrEqualTo($request->expires_at)
                || ! $this->isEligibleCustomer($customer)
            ) {
                return null;
            }

            $newEmail = mb_strtolower((string) $request->new_email);

            if (
                $newEmail === mb_strtolower((string) $customer->email)
                || User::withTrashed()
                    ->where('email', $newEmail)
                    ->whereKeyNot($customer->getKey())
                    ->exists()
            ) {
                $request->revoked_at = now();
                $request->save();

                return null;
            }

            $oldEmail = (string) $customer->email;

            $customer->forceFill([
                'email' => $newEmail,
            ])->save();

            $request->used_at = now();
            $request->save();

            CustomerEmailChangeRequest::query()
                ->where('user_id', $customer->getKey())
                ->whereKeyNot($request->getKey())
                ->whereNull('used_at')
                ->whereNull('revoked_at')
                ->update(['revoked_at' => now()]);

            $this->sessions->revokeAllAccessForUser($customer);

            $this->audit->record(new AuditEntryDTO(
                eventKey: 'identity.customer.email_changed',
                action: 'customer_email_changed',
                actorType: User::class,
                actorId: (string) $customer->getKey(),
                subjectType: User::class,
                subjectId: (string) $customer->getKey(),
                metadata: [
                    'request_id' => (string) $request->getKey(),
                    'access_revoked' => true,
                ],
            ));

            return [
                'customer_id' => (string) $customer->getKey(),
                'old_email' => $oldEmail,
            ];
        });

        if ($result === null) {
            return false;
        }

        try {
            Notification::route('mail', $result['old_email'])->notify(
                new CustomerEmailChangedNotification,
            );
        } catch (Throwable) {
            // The verified address change and access revocation remain committed.
        }

        return true;
    }

    private function assertEligibleCustomer(User $customer): void
    {
        if (! $this->isEligibleCustomer($customer)) {
            throw ValidationException::withMessages([
                'email' => 'Seul un compte client actif et vérifié peut modifier son adresse e-mail.',
            ]);
        }
    }

    private function isEligibleCustomer(User $customer): bool
    {
        return $customer->status === UserStatus::Active
            && $customer->email_verified_at !== null
            && $customer->hasRole('customer')
            && ! $customer->hasAnyRole([
                'super_admin_root',
                'super_admin',
                'cooperative_admin',
                'user',
            ]);
    }
}
