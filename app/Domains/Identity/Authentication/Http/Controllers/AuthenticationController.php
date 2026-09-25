<?php

declare(strict_types=1);

namespace App\Domains\Identity\Authentication\Http\Controllers;

use App\Domains\Identity\Authentication\DTOs\AuthenticationContext;
use App\Domains\Identity\Authentication\Http\Requests\AuthenticationRequest;
use App\Domains\Identity\Authentication\Services\AuthenticationService;
use App\Domains\Identity\Authentication\Services\LoginHistoryService;
use App\Domains\Identity\Authentication\Services\SessionService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use RuntimeException;

final class AuthenticationController
{
    public function __construct(
        private readonly AuthenticationService $authenticationService,
        private readonly SessionService $sessionService,
        private readonly LoginHistoryService $loginHistoryService,
    ) {
    }

    public function login(AuthenticationRequest $request): JsonResponse
    {
        $context = new AuthenticationContext(
            email: (string) $request->input('email'),
            password: (string) $request->input('password'),
            sessionId: $request->session()->getId(),
            ipAddress: $request->ip(),
            userAgent: $request->userAgent(),
            browser: $this->detectBrowser($request),
            device: $this->detectDevice($request),
        );

        try {
            $user = $this->authenticationService->authenticate(
                context: $context,
            );
        } catch (ModelNotFoundException) {
            return response()->json([
                'message' => 'Authentication failed.',
            ], 404);
        } catch (RuntimeException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
            ], 401);
        }

        /*
         * Authenticate the Laravel user.
         */
        Auth::login($user);

        /*
         * Regenerate the Laravel session ID after authentication.
         *
         * The AuthenticationSession must use this new ID.
         */
        $request->session()->regenerate();

        $newSessionId = $request->session()->getId();

        /*
         * Create the domain authentication session.
         *
         * Existing sessions are deliberately preserved.
         */
        $authenticationSession = $this->sessionService->create(
            user: $user,
            sessionId: $newSessionId,
            ipAddress: $context->ipAddress,
            userAgent: $context->userAgent,
            browser: $context->browser,
            device: $context->device,
        );

        /*
         * Record the successful authentication.
         */
        $this->loginHistoryService->recordSuccess(
            user: $user,
            session: $authenticationSession,
            ipAddress: $context->ipAddress,
            userAgent: $context->userAgent,
            browser: $context->browser,
            device: $context->device,
        );

        return response()->json([
            'message' => 'Authentication successful.',
            'user' => [
                'id' => $user->getKey(),
                'first_name' => $user->first_name,
                'last_name' => $user->last_name,
                'display_name' => $user->display_name,
                'email' => $user->email,
            ],
            'session' => [
                'id' => $authenticationSession->getKey(),
                'authenticated_at' => $authenticationSession->authenticated_at,
                'last_activity_at' => $authenticationSession->last_activity_at,
            ],
        ], 200);
    }

    public function logout(Request $request): JsonResponse
    {
        $user = Auth::user();

        if ($user === null) {
            return response()->json([
                'message' => 'Unauthenticated.',
            ], 401);
        }

        $currentSessionId = $request->session()->getId();

        $authenticationSession = $this->sessionService->current(
            user: $user,
            sessionId: $currentSessionId,
        );

        if ($authenticationSession === null) {
            return response()->json([
                'message' => 'Authentication session not found.',
            ], 401);
        }

        $revoked = $this->sessionService->revoke(
            session: $authenticationSession,
            reason: 'logout',
        );

        if (! $revoked) {
            return response()->json([
                'message' => 'Unable to logout.',
            ], 401);
        }

        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json([
            'message' => 'Logout successful.',
        ], 200);
    }

    private function detectBrowser(Request $request): ?string
    {
        $userAgent = strtolower((string) $request->userAgent());

        if ($userAgent === '') {
            return null;
        }

        return match (true) {
            str_contains($userAgent, 'edg') => 'Edge',
            str_contains($userAgent, 'chrome') => 'Chrome',
            str_contains($userAgent, 'firefox') => 'Firefox',
            str_contains($userAgent, 'safari') => 'Safari',
            str_contains($userAgent, 'opera') || str_contains($userAgent, 'opr') => 'Opera',
            default => 'Unknown',
        };
    }

    private function detectDevice(Request $request): ?string
    {
        $userAgent = strtolower((string) $request->userAgent());

        if ($userAgent === '') {
            return null;
        }

        return match (true) {
            str_contains($userAgent, 'iphone') => 'iPhone',
            str_contains($userAgent, 'ipad') => 'iPad',
            str_contains($userAgent, 'android') && str_contains($userAgent, 'mobile') => 'Android Phone',
            str_contains($userAgent, 'android') => 'Android Tablet',
            str_contains($userAgent, 'windows') => 'Windows',
            str_contains($userAgent, 'macintosh') => 'Mac',
            str_contains($userAgent, 'linux') => 'Linux',
            default => 'Unknown',
        };
    }
}