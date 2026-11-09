<?php

declare(strict_types=1);

namespace App\Domains\Identity\Authentication\Http\Controllers;

use App\Domains\Identity\Authentication\DTOs\AuthenticationContext;
use App\Domains\Identity\Authentication\Exceptions\AuthenticationFailedException;
use App\Domains\Identity\Authentication\Http\Requests\AuthenticationRequest;
use App\Domains\Identity\Authentication\Services\SessionService;
use App\Domains\Identity\Authentication\Workflows\AuthenticationWorkflow;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

final class AuthenticationController
{
    public function __construct(
        private readonly AuthenticationWorkflow $authenticationWorkflow,
        private readonly SessionService $sessionService,
    ) {}

    public function showLogin(): View|RedirectResponse
    {
        if (Auth::check()) {
            $user = Auth::user();

            if ($user?->must_change_password) {
                return redirect()->route('password.force-change.edit');
            }

            if ($user?->hasRole('super_admin_root')) {
                return redirect()->route('root.backoffice.dashboard');
            }

            if ($user?->hasRole('user')) {
                return redirect()->route('user.dashboard');
            }

            if ($user?->hasAnyRole(['super_admin', 'cooperative_admin'])) {
                return redirect()->route('backoffice.dashboard');
            }

            abort(403);
        }

        return view('auth.login');
    }

    public function login(
        AuthenticationRequest $request,
    ): JsonResponse|RedirectResponse {
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
            $authenticationSession = $this->authenticationWorkflow->execute(
                context: $context,
                sessionId: $context->sessionId,
                request: $request,
            );
        } catch (ModelNotFoundException) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'Authentication failed.',
                ], 401);
            }

            return back()
                ->withErrors([
                    'email' => 'Authentication failed.',
                ])
                ->withInput($request->only('email'));
        } catch (AuthenticationFailedException) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'Authentication failed.',
                ], 401);
            }

            return back()
                ->withErrors([
                    'email' => 'Authentication failed.',
                ])
                ->withInput($request->only('email'));
        }

        $user = Auth::user();

        if ($user === null) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'Authentication failed.',
                ], 401);
            }

            return back()
                ->withErrors([
                    'email' => 'Authentication failed.',
                ])
                ->withInput($request->only('email'));
        }

        if ($request->expectsJson()) {
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

        /*
         * Keep the session lifecycle managed by the existing workflow.
         * Do not regenerate or revoke sessions here without synchronizing
         * the Laravel session ID with the Identity authentication session.
         */
        if ($user->must_change_password) {
            return redirect()->route('password.force-change.edit');
        }

        if ($user->hasRole('super_admin_root')) {
            return redirect()->route('root.backoffice.dashboard');
        }

        if ($user->hasRole('user')) {
            return redirect()->route('user.dashboard');
        }

        if ($user->hasAnyRole(['super_admin', 'cooperative_admin'])) {
            return redirect()->route('backoffice.dashboard');
        }

        abort(403);
    }

    public function logout(
        Request $request,
    ): JsonResponse|RedirectResponse {
        $user = Auth::user();

        if ($user === null) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'Unauthenticated.',
                ], 401);
            }

            return redirect()->route('authentication.login');
        }

        /*
         * Revoke only the current Identity authentication session when it
         * exists. A missing/stale Identity record must never keep the
         * Laravel web session authenticated.
         */
        $authenticationSessionId = $request->session()->get(
            'authentication_session_id',
        );

        if (is_string($authenticationSessionId) && $authenticationSessionId !== '') {
            $authenticationSession = $this->sessionService->findActiveById(
                user: $user,
                authenticationSessionId: $authenticationSessionId,
            );

            if ($authenticationSession !== null) {
                $this->sessionService->revoke(
                    session: $authenticationSession,
                    reason: 'logout',
                );
            }
        }

        Auth::logout();

        $request->session()->forget('authentication_session_id');
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Logout successful.',
            ], 200);
        }

        return redirect()->route('authentication.login');
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
            str_contains($userAgent, 'opera')
                || str_contains($userAgent, 'opr') => 'Opera',
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
            str_contains($userAgent, 'android')
                && str_contains($userAgent, 'mobile') => 'Android Phone',
            str_contains($userAgent, 'android') => 'Android Tablet',
            str_contains($userAgent, 'windows') => 'Windows',
            str_contains($userAgent, 'macintosh') => 'Mac',
            str_contains($userAgent, 'linux') => 'Linux',
            default => 'Unknown',
        };
    }
}
