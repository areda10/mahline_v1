<?php

declare(strict_types=1);

namespace App\Domains\Identity\Password\Services;

use App\Domains\Identity\Users\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use InvalidArgumentException;

final class PasswordHistoryService
{
    public const MAX_PREVIOUS_PASSWORDS = 5;

    /**
     * Reject the current password and any of the five most recently
     * replaced passwords. The caller must hold a lock on the user row
     * and execute the password update in a database transaction.
     */
    public function assertNotReused(User $user, string $candidate): void
    {
        if (Hash::check($candidate, (string) $user->password)) {
            throw new InvalidArgumentException(
                'The new password must not match the current password or any of the five previous passwords.'
            );
        }

        $history = DB::table('user_password_histories')
            ->where('user_id', $user->getKey())
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->lockForUpdate()
            ->get(['password_hash']);

        foreach ($history as $entry) {
            if (Hash::check($candidate, (string) $entry->password_hash)) {
                throw new InvalidArgumentException(
                    'The new password must not match the current password or any of the five previous passwords.'
                );
            }
        }
    }

    /**
     * Archive the current hash and retain only the five newest entries.
     * This must run in the same transaction as the successful password
     * replacement so a rollback cannot pollute the history.
     */
    public function archiveCurrentPassword(User $user): void
    {
        $currentHash = (string) $user->password;

        if ($currentHash === '') {
            throw new InvalidArgumentException(
                'A current password hash is required before it can be archived.'
            );
        }

        DB::table('user_password_histories')->insert([
            'id' => (string) Str::ulid(),
            'user_id' => $user->getKey(),
            'password_hash' => $currentHash,
            'created_at' => now(),
        ]);

        $retainedIds = DB::table('user_password_histories')
            ->where('user_id', $user->getKey())
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit(self::MAX_PREVIOUS_PASSWORDS)
            ->pluck('id');

        DB::table('user_password_histories')
            ->where('user_id', $user->getKey())
            ->whereNotIn('id', $retainedIds)
            ->delete();
    }
}
