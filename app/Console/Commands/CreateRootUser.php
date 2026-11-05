<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domains\Audit\Services\AuditService;
use App\Domains\Identity\Authorization\Models\Role;
use App\Domains\Identity\Enums\UserStatus;
use App\Domains\Identity\Users\Models\User;
use App\Shared\DTOs\AuditEntryDTO;
use DomainException;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

final class CreateRootUser extends Command
{
    protected $signature = 'mahline:create-root';

    protected $description = 'Create the initial MAHLINE super admin root account.';

    public function handle(): int
    {
        if ($this->rootAccountExists()) {
            $this->components->error(
                'A super_admin_root account already exists. Use the existing account recovery process.',
            );

            return self::FAILURE;
        }

        $role = Role::query()
            ->where('slug', 'super_admin_root')
            ->where('is_active', true)
            ->first();

        if ($role === null) {
            $this->components->error(
                'The active super_admin_root role is missing. Run: php artisan db:seed --class=RoleSeeder',
            );

            return self::FAILURE;
        }

        $firstName = trim((string) $this->ask('Prénom'));
        $lastName = trim((string) $this->ask('Nom'));
        $email = mb_strtolower(trim((string) $this->ask('Adresse e-mail')));
        $telephone = trim((string) $this->ask('Téléphone'));
        $password = (string) $this->secret('Mot de passe');

        $validator = Validator::make(
            [
                'first_name' => $firstName,
                'last_name' => $lastName,
                'email' => $email,
                'telephone' => $telephone,
                'password' => $password,
            ],
            [
                'first_name' => ['required', 'string', 'max:100'],
                'last_name' => ['required', 'string', 'max:100'],
                'email' => ['required', 'string', 'email', 'max:255'],
                'telephone' => ['required', 'string', 'max:30'],
                'password' => ['required', 'string', 'min:12'],
            ],
        );

        if ($validator->fails()) {
            $this->components->error($validator->errors()->first());

            return self::FAILURE;
        }

        try {
            $user = DB::transaction(function () use (
                $role,
                $firstName,
                $lastName,
                $email,
                $telephone,
                $password,
            ): User {
                // Serialise bootstrap attempts on the role row.
                $lockedRole = Role::query()
                    ->whereKey($role->getKey())
                    ->where('is_active', true)
                    ->lockForUpdate()
                    ->first();

                if ($lockedRole === null) {
                    throw new DomainException(
                        'The active super_admin_root role is no longer available.',
                    );
                }

                if ($this->rootAccountExists()) {
                    throw new DomainException(
                        'A super_admin_root account already exists.',
                    );
                }

                if (User::withTrashed()->where('email', $email)->exists()) {
                    throw new DomainException(
                        'This email address is already used by an account.',
                    );
                }

                $user = User::query()->create([
                    'first_name' => $firstName,
                    'last_name' => $lastName,
                    'display_name' => $firstName.' '.$lastName,
                    'email' => $email,
                    'telephone' => $telephone,
                    'password' => $password,
                    'status' => UserStatus::Active,
                        'cooperative_id' => null,
                    'locale' => 'fr',
                    'timezone' => 'UTC',
                ]);

                $user->forceFill(['email_verified_at' => now()])->save();

                $user->roles()->attach($lockedRole->getKey());

                app(AuditService::class)->record(new AuditEntryDTO(
                    eventKey: 'identity.user.root_bootstrapped',
                    action: 'created',
                    subjectType: User::class,
                    subjectId: (string) $user->getKey(),
                    newValues: [
                        'role' => 'super_admin_root',
                        'status' => UserStatus::Active->value,
                        'email_verified' => true,
                        'cooperative_id' => null,
                    ],
                    metadata: ['source' => 'artisan_bootstrap_command'],
                ));

                return $user;
            });
        } catch (DomainException $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->components->info(
            sprintf('Root account created successfully: %s', $user->email),
        );

        return self::SUCCESS;
    }

    private function rootAccountExists(): bool
    {
        return User::withTrashed()
            ->whereHas('roles', fn ($query) => $query->where('slug', 'super_admin_root'))
            ->exists();
    }
}
