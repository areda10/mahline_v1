<?php

declare(strict_types=1);

namespace App\Domains\Identity\Authentication\Http\Controllers;

use App\Domains\Audit\Services\AuditService;
use App\Domains\Identity\Authentication\Services\AuthenticationSecurityService;
use App\Domains\Identity\Users\Models\User;
use App\Shared\DTOs\AuditEntryDTO;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final class AuthenticationLockoutController
{
    public function unlock(
        Request $request,
        AuthenticationSecurityService $securityService,
        AuditService $auditService,
    ): RedirectResponse {
        abort_unless($request->user()?->hasRole('super_admin_root'), 403);

        $data = $request->validate([
            'email' => ['required', 'string', 'email', 'max:255'],
            'ip_address' => ['required', 'ip'],
            'reason' => ['required', 'string', 'min:10', 'max:500'],
        ]);

        $email = mb_strtolower(trim($data['email']));
        $ipAddress = trim($data['ip_address']);
        $actor = $request->user();
        $target = User::query()->where('email', $email)->first();

        // Record the administrative intent before mutating cache-based counters.
        $auditService->record(new AuditEntryDTO(
            eventKey: 'identity.authentication.lockouts.unlocked',
            action: 'unlock',
            actorType: User::class,
            actorId: (string) $actor->getKey(),
            subjectType: $target === null ? null : User::class,
            subjectId: $target === null ? null : (string) $target->getKey(),
            metadata: [
                'reason' => trim($data['reason']),
                'cleared_scopes' => ['account', 'ip', 'ip_email'],
                'email_fingerprint' => hash_hmac('sha256', $email, (string) config('app.key')),
                'ip_fingerprint' => hash_hmac('sha256', $ipAddress, (string) config('app.key')),
                'target_account_found' => $target !== null,
            ],
            ipAddress: $request->ip(),
            userAgent: $request->userAgent(),
        ));

        $securityService->unlockAllScopes($email, $ipAddress);

        return back()->with(
            'success',
            'Les blocages du compte, de l’adresse IP et du couple IP + e-mail ont été levés.',
        );
    }
}
