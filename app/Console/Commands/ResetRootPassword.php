<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domains\Audit\Services\AuditService;
use App\Domains\Identity\Users\Models\User;
use App\Shared\DTOs\AuditEntryDTO;
use DomainException;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Throwable;

final class ResetRootPassword extends Command
{
    protected $signature = 'mahline:reset-root-password';

    protected $description = 'Reset the password of an existing super admin root account.';

    public function handle(): int
    {
        $email = mb_strtolower(trim((string) $this->ask('Adresse e-mail du compte Root')));

        $validator = Validator::make(
            ['email' => $email],
            ['email' => ['required', 'string', 'email', 'max:255']],
        );

        if ($validator->fails()) {
            $this->components->error($validator->errors()->first());

            return self::FAILURE;
        }

        $user = User::withTrashed()->where('email', $email)->first();

        if ($user === null) {
            $this->components->error('Aucun compte ne correspond à cette adresse e-mail.');

            return self::FAILURE;
        }

        if ($user->trashed()) {
            $this->components->error('Ce compte est supprimé. La réinitialisation est refusée.');

            return self::FAILURE;
        }

        if (! $user->roles()->where('slug', 'super_admin_root')->exists()) {
            $this->components->error('Ce compte ne possède pas le rôle super_admin_root.');

            return self::FAILURE;
        }

        $password = (string) $this->secret('Nouveau mot de passe');
        $confirmation = (string) $this->secret('Confirmer le nouveau mot de passe');

        $passwordValidator = Validator::make(
            ['password' => $password, 'password_confirmation' => $confirmation],
            [
                'password' => ['required', 'string', 'min:12', 'same:password_confirmation'],
                'password_confirmation' => ['required', 'string'],
            ],
        );

        if ($passwordValidator->fails()) {
            $this->components->error($passwordValidator->errors()->first());

            return self::FAILURE;
        }

        try {
            DB::transaction(function () use ($email, $password): void {
                $lockedUser = User::withTrashed()
                    ->where('email', $email)
                    ->lockForUpdate()
                    ->first();

                if ($lockedUser === null || $lockedUser->trashed()) {
                    throw new DomainException('The Root account is no longer available. No changes were made.');
                }

                if (! $lockedUser->roles()->where('slug', 'super_admin_root')->exists()) {
                    throw new DomainException('The account no longer has the super_admin_root role. No changes were made.');
                }

                $lockedUser->password = $password;
                $lockedUser->save();

                app(AuditService::class)->record(new AuditEntryDTO(
                    eventKey: 'identity.user.root_password_reset',
                    action: 'password_reset',
                    subjectType: User::class,
                    subjectId: (string) $lockedUser->getKey(),
                    newValues: ['password_changed' => true],
                    metadata: ['source' => 'artisan_reset_root_password_command'],
                ));
            });
        } catch (DomainException $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        } catch (Throwable) {
            $this->components->error('La réinitialisation a échoué. La transaction a été annulée.');

            return self::FAILURE;
        }

        $this->components->info('Le mot de passe du compte Root a été réinitialisé.');

        return self::SUCCESS;
    }
}
