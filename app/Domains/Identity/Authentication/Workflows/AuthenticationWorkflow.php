<?php

declare(strict_types=1);

namespace App\Domains\Identity\Authentication\Workflows;

use App\Domains\Identity\Authentication\DTOs\AuthenticationContext;
use App\Domains\Identity\Authentication\Exceptions\AuthenticationFailedException;
use App\Domains\Identity\Authentication\Models\AuthenticationSession;
use App\Domains\Identity\Authentication\Services\AuthenticationService;
use App\Domains\Identity\Authentication\Services\LoginHistoryService;
use App\Domains\Identity\Authentication\Services\SessionService;
use App\Domains\Identity\Authentication\Services\UnusualActivityDetectionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Throwable;

final class AuthenticationWorkflow
{
    public function __construct(
        private readonly AuthenticationService $authenticationService,
        private readonly SessionService $sessionService,
        private readonly LoginHistoryService $loginHistoryService,
        private readonly UnusualActivityDetectionService $unusualActivityDetectionService,
        private readonly AuthenticationWorkflowHook $hook,
    ) {}

    public function execute(
        AuthenticationContext $context,
        string $sessionId,
        ?Request $request = null,
        bool $customerOnly = false,
    ): AuthenticationSession {
        $request ??= request();
        $user = $this->authenticationService->authenticate(
            context: $context,
        );

        // A customer URL must never create a session for an internal account.
        // Keep the public authentication response identical for every mismatch.
        if ($customerOnly && (
            ! $user->hasRole('customer')
            || $user->hasAnyRole([
                'super_admin_root',
                'super_admin',
                'cooperative_admin',
                'user',
            ])
        )) {
            throw new AuthenticationFailedException();
        }

        try {
            Auth::login($user);

            $request->session()->regenerate();

            $newSessionId = $request->session()->getId();

            [$authenticationSession, $isNewDevice] = DB::transaction(
                function () use (
                    $user,
                    $context,
                    $newSessionId,
                ): array {
                    $authenticationSession = $this->sessionService->create(
                        user: $user,
                        sessionId: $newSessionId,
                        ipAddress: $context->ipAddress,
                        userAgent: $context->userAgent,
                        browser: $context->browser,
                        device: $context->device,
                    );

                    $isNewDevice = $context->device !== null
                        && $this->unusualActivityDetectionService->isNewDevice(
                            user: $user,
                            device: $context->device,
                        );

                    if ($context->device !== null) {
                        $this->unusualActivityDetectionService->rememberDevice(
                            user: $user,
                            device: $context->device,
                        );
                    }

                    $this->hook->afterDeviceRemembered(
                        user: $user,
                        session: $authenticationSession,
                        isNewDevice: $isNewDevice,
                    );

                    $this->loginHistoryService->recordSuccess(
                        user: $user,
                        session: $authenticationSession,
                        ipAddress: $context->ipAddress,
                        userAgent: $context->userAgent,
                        browser: $context->browser,
                        device: $context->device,
                    );

                    return [$authenticationSession, $isNewDevice];
                },
            );

            $this->hook->afterAuthenticationCommitted(
                user: $user,
                session: $authenticationSession,
                isNewDevice: $isNewDevice,
            );

            $request->session()->put(
                'authentication_session_id',
                $authenticationSession->getKey(),
            );

            return $authenticationSession;
        } catch (Throwable $exception) {
            Auth::logout();

            $request->session()->invalidate();
            $request->session()->regenerateToken();

            throw $exception;
        }
    }
}
