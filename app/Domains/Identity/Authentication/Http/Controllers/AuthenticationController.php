<?php

declare(strict_types=1);

namespace App\Domains\Identity\Authentication\Http\Controllers;

use App\Core\Foundation\Http\Controllers\BaseController;
use App\Domains\Identity\Authentication\DTOs\AuthenticationContext;
use App\Domains\Identity\Authentication\Http\Requests\AuthenticationRequest;
use App\Domains\Identity\Authentication\Services\AuthenticationService;
use App\Domains\Identity\Authentication\Services\LoginHistoryService;
use App\Domains\Identity\Authentication\Services\SessionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

final class AuthenticationController extends BaseController
{
    public function __construct(
        private readonly AuthenticationService $authenticationService,
        private readonly SessionService $sessionService,
        private readonly LoginHistoryService $loginHistoryService,
    ) {
    }

    /**
     * Display the login form.
     */
    public function showLoginForm(): View
    {
        return view('auth.login');
    }

    /**
     * Authenticate the user.
     */
    public function authenticate(
        AuthenticationRequest $request,
    ): RedirectResponse {
        /*
         * Build the authentication context.
         *
         * The service layer remains independent from
         * Laravel's HTTP Request.
         */
        $context = new AuthenticationContext(
            email: $request->validated('email'),
            password: $request->validated('password'),
            sessionId: $request->session()->getId(),
            ipAddress: $request->ip(),
            userAgent: $request->userAgent(),
            browser: $this->detectBrowser($request->userAgent()),
            device: $this->detectDevice($request->userAgent()),
        );

        /*
         * Authenticate the user through the domain service.
         */
        $user = $this->authenticationService->authenticate(
            context: $context,
        );

        /*
         * Authenticate the user in Laravel's guard.
         */
        Auth::login($user);

        /*
         * Regenerate the Laravel session ID after authentication
         * to prevent session fixation.
         */
        $request->session()->regenerate();

        return redirect()->intended('/');
    }

    /**
     * Logout the currently authenticated user.
     */
    public function logout(Request $request): RedirectResponse
    {
        $user = $request->user();

        if ($user !== null) {
            /*
             * Revoke the MAHLINE authentication session.
             */
            $session = $this->sessionService->findActiveForUser($user);

            if ($session !== null) {
                $this->sessionService->revoke(
                    session: $session,
                    reason: 'logout',
                );

                /*
                 * Record logout in login history.
                 */
                $this->loginHistoryService->recordLogout(
                    user: $user,
                    session: $session,
                    ipAddress: $request->ip(),
                    userAgent: $request->userAgent(),
                    browser: $this->detectBrowser($request->userAgent()),
                    device: $this->detectDevice($request->userAgent()),
                );
            }
        }

        /*
         * Logout from Laravel's authentication guard.
         */
        Auth::logout();

        /*
         * Invalidate the HTTP session.
         */
        $request->session()->invalidate();

        /*
         * Regenerate the CSRF token.
         */
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }

    /**
     * Detect the browser from the User-Agent.
     *
     * This is intentionally kept simple for the first version.
     */
    private function detectBrowser(?string $userAgent): ?string
    {
        if ($userAgent === null) {
            return null;
        }

        return match (true) {
            str_contains($userAgent, 'Firefox') => 'Firefox',
            str_contains($userAgent, 'Edg') => 'Edge',
            str_contains($userAgent, 'Chrome') => 'Chrome',
            str_contains($userAgent, 'Safari') => 'Safari',
            default => 'Unknown',
        };
    }

    /**
     * Detect the client device.
     */
    private function detectDevice(?string $userAgent): ?string
    {
        if ($userAgent === null) {
            return null;
        }

        return match (true) {
            str_contains($userAgent, 'iPhone') => 'iPhone',
            str_contains($userAgent, 'Android') => 'Android',
            str_contains($userAgent, 'iPad') => 'iPad',
            str_contains($userAgent, 'Windows') => 'Windows',
            str_contains($userAgent, 'Macintosh') => 'Mac',
            default => 'Unknown',
        };
    }
}