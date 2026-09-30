<?php

declare(strict_types=1);

namespace App\Domains\Identity\Authentication\Workflows;

use App\Domains\Identity\Authentication\DTOs\AuthenticationContext;
use App\Domains\Identity\Authentication\Models\AuthenticationSession;
use App\Domains\Identity\Authentication\Services\AuthenticationService;
use App\Domains\Identity\Authentication\Services\LoginHistoryService;
use App\Domains\Identity\Authentication\Services\SessionService;
use App\Domains\Identity\Authentication\Services\UnusualActivityDetectionService;
use App\Domains\Identity\Users\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;


final class AuthenticationWorkflow
{
    public function __construct(
        private readonly AuthenticationService $authenticationService,
        private readonly SessionService $sessionService,
        private readonly LoginHistoryService $loginHistoryService,
        private readonly UnusualActivityDetectionService $unusualActivityDetectionService,
        private readonly AuthenticationWorkflowHook $hook,
    ) {
    }

    public function execute(
        AuthenticationContext $context,
        string $sessionId,
    ): AuthenticationSession {
        $user = $this->authenticationService->authenticate(
            context: $context,
        );

        Auth::login($user);

        /*
         * Laravel's authenticated session must be regenerated before
         * creating the domain AuthenticationSession.
         */
        request()->session()->regenerate();

        $newSessionId = request()->session()->getId();

        $authenticationSession = DB::transaction(
            function () use (
                $user,
                $context,
                $newSessionId,
            ): AuthenticationSession {
                $authenticationSession = $this->sessionService->create(
                    user: $user,
                    sessionId: $newSessionId,
                    ipAddress: $context->ipAddress,
                    userAgent: $context->userAgent,
                    browser: $context->browser,
                    device: $context->device,
                );

                if ($context->device !== null) {
                    $this->unusualActivityDetectionService->rememberDevice(
                        user: $user,
                        device: $context->device,
                    );
                }

                /*
                 * Controlled failure point used by the atomicity test.
                 *
                 * In production the default hook does nothing.
                 */
                $this->hook->afterDeviceRemembered(
                    user: $user,
                    session: $authenticationSession,
                );

                $this->loginHistoryService->recordSuccess(
                    user: $user,
                    session: $authenticationSession,
                    ipAddress: $context->ipAddress,
                    userAgent: $context->userAgent,
                    browser: $context->browser,
                    device: $context->device,
                );

                return $authenticationSession;
            },
        );

        request()->session()->put(
            'authentication_session_id',
            $authenticationSession->getKey(),
        );

        return $authenticationSession;
    }
}
