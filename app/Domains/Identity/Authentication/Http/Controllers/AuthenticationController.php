<?php

declare(strict_types=1);

namespace App\Domains\Identity\Authentication\Http\Controllers;

use App\Core\Foundation\Http\Controllers\BaseController;
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

/**
 * Authentication Controller.
 *
 * Responsible only for the HTTP layer of authentication.
 *
 * The controller:
 *
 * - receives the HTTP request;
 * - validates the authentication data;
 * - builds AuthenticationContext;
 * - delegates authentication to AuthenticationService;
 * - synchronizes Laravel Auth;
 * - returns the HTTP response.
 *
 * Business rules remain inside domain services.
 *
 * Architecture rule:
 *
 * ONE USER → ONE ACTIVE AUTHENTICATED SESSION
 */
final class AuthenticationController extends BaseController
{
    public function __construct(
        private readonly AuthenticationService $authenticationService,
        private readonly SessionService $sessionService,
        private readonly LoginHistoryService $loginHistoryService,
    ) {
    }

    /**
     * Display the authentication endpoint.
     */
    public function showLogin(): JsonResponse
    {
        return response()->json([
            'message' => 'Authentication endpoint.',
        ]);
    }

    /**
     * Authenticate a user.
     */
    public function login(
        AuthenticationRequest $request,
    ): JsonResponse {
        /*
         * AuthenticationRequest has already validated
         * the incoming data.
         *
         * validated() already returns an array.
         *
         * IMPORTANT:
         *
         * Do not use:
         *
         * $request->validated()->all()
         *
         * because validated() returns an array.
         */
        $validated = $request->validated();

        /*
         * Regenerate the Laravel session before creating
         * the AuthenticationContext.
         *
         * This prevents session fixation.
         */
        $request->session()->regenerate();

        /*
         * Get the final Laravel session identifier.
         */
        $sessionId = $request->session()->getId();

        /*
         * Build the domain DTO.
         */
        $context = new AuthenticationContext(
            email: (string) $validated['email'],
            password: (string) $validated['password'],
            sessionId: $sessionId,
            ipAddress: $request->ip(),
            userAgent: $request->userAgent(),
            browser: $this->detectBrowser($request),
            device: $this->detectDevice($request),
        );

        /*
         * Delegate authentication to the domain service.
         */
        try {
            $user = $this->authenticationService->authenticate(
                context: $context,
            );
        } catch (ModelNotFoundException) {
            /*
             * Unknown email.
             *
             * AuthenticationService has already recorded
             * the failed attempt.
             */
            return response()->json([
                'message' => 'User not found.',
            ], 404);
        } catch (RuntimeException $exception) {
            /*
             * Known authentication failure.
             *
             * Examples:
             *
             * - invalid credentials
             * - inactive account
             */
            return response()->json([
                'message' => $exception->getMessage(),
            ], 401);
        }

        /*
         * Synchronize Laravel's authentication guard.
         */
        Auth::login($user);

        /*
         * Retrieve the active domain authentication session.
         */
        $authenticationSession = $this->sessionService->current(
            user: $user,
        );

        return response()->json([
            'message' => 'Authentication successful.',

            'user' => [
                'id' => $user->getKey(),
                'email' => $user->email,
                'display_name' => $user->display_name,
                'status' => $user->status,
            ],

            'session' => [
                'id' => $authenticationSession?->getKey(),
                'authenticated_at' => $authenticationSession?->authenticated_at,
                'last_activity_at' => $authenticationSession?->last_activity_at,
            ],
        ], 200);
    }

    /**
     * Logout the currently authenticated user.
     */
    public function logout(
        Request $request,
    ): JsonResponse {
        /*
         * Retrieve the authenticated user.
         */
        $user = Auth::user();

        if ($user === null) {
            return response()->json([
                'message' => 'No authenticated user.',
            ], 401);
        }

        /*
         * Retrieve the active authentication session before
         * revoking it.
         */
        $authenticationSession = $this->sessionService->current(
            user: $user,
        );

        /*
         * Revoke the domain authentication session.
         */
        $revoked = $this->sessionService->revokeForUser(
            user: $user,
            reason: 'logout',
        );

        /*
         * Record logout history.
         *
         * SessionService records the revocation itself.
         * This explicit logout event gives us a dedicated
         * "logout" history event.
         */
        if (
            $revoked
            && $authenticationSession !== null
        ) {
            $this->loginHistoryService->recordLogout(
                user: $user,
                session: $authenticationSession,
                ipAddress: $request->ip(),
                userAgent: $request->userAgent(),
                browser: $this->detectBrowser($request),
                device: $this->detectDevice($request),
            );
        }

        /*
         * Logout from Laravel's authentication guard.
         */
        Auth::logout();

        /*
         * Invalidate the Laravel session.
         */
        $request->session()->invalidate();

        /*
         * Generate a new CSRF token.
         */
        $request->session()->regenerateToken();

        return response()->json([
            'message' => 'Logout successful.',
        ], 200);
    }

    /**
     * Detect the browser from the User-Agent.
     */
    private function detectBrowser(
        Request $request,
    ): ?string {
        $userAgent = $request->userAgent();

        if ($userAgent === null) {
            return null;
        }

        /*
         * Edge must be checked before Chrome because
         * Edge User-Agent strings contain Chrome.
         */
        if (
            str_contains($userAgent, 'Edg/')
            || str_contains($userAgent, 'Edge/')
        ) {
            return 'Edge';
        }

        if (str_contains($userAgent, 'Firefox/')) {
            return 'Firefox';
        }

        if (
            str_contains($userAgent, 'OPR/')
            || str_contains($userAgent, 'Opera/')
        ) {
            return 'Opera';
        }

        if (
            str_contains($userAgent, 'Chrome/')
            && ! str_contains($userAgent, 'Edg/')
        ) {
            return 'Chrome';
        }

        if (
            str_contains($userAgent, 'Safari/')
            && ! str_contains($userAgent, 'Chrome/')
        ) {
            return 'Safari';
        }

        if (str_contains($userAgent, 'MSIE')) {
            return 'Internet Explorer';
        }

        return 'Unknown';
    }

    /**
     * Detect the client device from the User-Agent.
     */
    private function detectDevice(
        Request $request,
    ): ?string {
        $userAgent = $request->userAgent();

        if ($userAgent === null) {
            return null;
        }

        if (str_contains($userAgent, 'iPhone')) {
            return 'iPhone';
        }

        if (str_contains($userAgent, 'iPad')) {
            return 'iPad';
        }

        if (str_contains($userAgent, 'Android')) {
            return 'Android';
        }

        if (
            str_contains($userAgent, 'Windows NT')
            || str_contains($userAgent, 'Windows')
        ) {
            return 'Windows';
        }

        if (
            str_contains($userAgent, 'Macintosh')
            || str_contains($userAgent, 'Mac OS X')
        ) {
            return 'macOS';
        }

        if (str_contains($userAgent, 'Linux')) {
            return 'Linux';
        }

        return 'Unknown';
    }
}