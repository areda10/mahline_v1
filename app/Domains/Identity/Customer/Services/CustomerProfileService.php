<?php

declare(strict_types=1);

namespace App\Domains\Identity\Customer\Services;

use App\Core\Foundation\Services\BaseService;
use App\Domains\Audit\Services\AuditService;
use App\Domains\Identity\Enums\UserStatus;
use App\Domains\Identity\Users\Models\User;
use App\Shared\DTOs\AuditEntryDTO;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

final class CustomerProfileService extends BaseService
{
    public function __construct(
        private readonly AuditService $audit,
    ) {}

    /**
     * Update the editable personal information of the authenticated customer.
     *
     * Email changes and profile media are handled by their dedicated flows.
     *
     * @param array{first_name: string, last_name: string, telephone: string} $attributes
     */
    public function update(User $customer, array $attributes): User
    {
        return DB::transaction(function () use ($customer, $attributes): User {
            $lockedCustomer = User::query()
                ->whereKey($customer->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if (
                ! $lockedCustomer->hasRole('customer')
                || $lockedCustomer->hasAnyRole([
                    'super_admin_root',
                    'super_admin',
                    'cooperative_admin',
                    'user',
                ])
                || $lockedCustomer->status !== UserStatus::Active
                || $lockedCustomer->email_verified_at === null
            ) {
                throw new AuthorizationException(
                    'Only an active, verified customer can update this profile.',
                );
            }

            $oldValues = [
                'first_name' => $lockedCustomer->first_name,
                'last_name' => $lockedCustomer->last_name,
                'display_name' => $lockedCustomer->display_name,
                'telephone' => $lockedCustomer->telephone,
            ];

            $firstName = trim($attributes['first_name']);
            $lastName = trim($attributes['last_name']);
            $telephone = trim($attributes['telephone']);

            $lockedCustomer->forceFill([
                'first_name' => $firstName,
                'last_name' => $lastName,
                'display_name' => trim($firstName.' '.$lastName),
                'telephone' => $telephone,
            ])->save();

            $this->audit->record(new AuditEntryDTO(
                eventKey: 'identity.customer.profile_updated',
                action: 'customer_profile_updated',
                actorType: User::class,
                actorId: (string) $lockedCustomer->getKey(),
                subjectType: User::class,
                subjectId: (string) $lockedCustomer->getKey(),
                oldValues: $oldValues,
                newValues: [
                    'first_name' => $firstName,
                    'last_name' => $lastName,
                    'display_name' => trim($firstName.' '.$lastName),
                    'telephone' => $telephone,
                ],
            ));

            return $lockedCustomer->fresh();
        });
    }
}
