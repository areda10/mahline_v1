<?php
declare(strict_types=1);
namespace App\Domains\Identity\Users\Services;
use App\Core\Foundation\Services\BaseService;
use App\Domains\Audit\Contracts\AuditWriter;
use App\Domains\Identity\Users\Models\User;
use App\Shared\DTOs\AuditEntryDTO;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
final class UserProfileService extends BaseService
{
    public function __construct(private readonly AuditWriter $audit) {}
    /**
     * Update only the authenticated internal user's personal information.
     * Email, password, role, status and cooperative assignment use dedicated workflows.
     * @param array{first_name: string, last_name: string, telephone: string} $attributes
     */
    public function updateOwnProfile(User $user, array $attributes): User
    {
        return DB::transaction(function () use ($user, $attributes): User {
            $lockedUser = User::query()->whereKey($user->getKey())->lockForUpdate()->firstOrFail();
            if (! $lockedUser->isAuthorized() || ! $lockedUser->hasAnyRole(['cooperative_admin', 'user'])) {
                throw new AuthorizationException('Only an active, authorized cooperative administrator or user can update this profile.');
            }
            $firstName = trim($attributes['first_name']);
            $lastName = trim($attributes['last_name']);
            $telephone = trim($attributes['telephone']);
            $displayName = trim($firstName.' '.$lastName);
            $oldValues = [
                'first_name' => $lockedUser->first_name,
                'last_name' => $lockedUser->last_name,
                'display_name' => $lockedUser->display_name,
                'telephone' => $lockedUser->telephone,
            ];
            $lockedUser->forceFill([
                'first_name' => $firstName,
                'last_name' => $lastName,
                'display_name' => $displayName,
                'telephone' => $telephone,
            ])->save();
            $this->audit->record(new AuditEntryDTO(
                eventKey: 'identity.user.profile_updated',
                action: 'user_profile_updated',
                actorType: User::class,
                actorId: (string) $lockedUser->getKey(),
                subjectType: User::class,
                subjectId: (string) $lockedUser->getKey(),
                oldValues: $oldValues,
                newValues: [
                    'first_name' => $firstName,
                    'last_name' => $lastName,
                    'display_name' => $displayName,
                    'telephone' => $telephone,
                ],
            ));
            return $lockedUser->fresh();
        });
    }
}
