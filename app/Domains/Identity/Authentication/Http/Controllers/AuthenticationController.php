<?php

declare(strict_types=1);

namespace App\Domains\Identity\Authentication\Http\Controllers;

use App\Domains\Identity\Authentication\DTOs\AuthenticationContext;
use App\Domains\Identity\Authentication\Http\Requests\AuthenticationRequest;
use App\Domains\Identity\Authentication\Services\SessionService;
use App\Domains\Identity\Authentication\Workflows\AuthenticationWorkflow;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;

final class AuthenticationController
{
    public function __construct(
        private readonly AuthenticationWorkflow $authenticationWorkflow,
        private readonly SessionService $sessionService,
    ) {
    }


    public function showLogin(): \Illuminate\Contracts\View\View|\Illuminate\Http\RedirectResponse
    {
        if (Auth::check()) {
            return redirect()->intended('/');
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
            );
        } catch (ModelNotFoundException) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'Authentication failed.',
                ], 404);
            }

            return back()
                ->withErrors([
                    'email' => 'Authentication failed.',
                ])
                ->withInput($request->only('email'));
        } catch (RuntimeException $exception) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => $exception->getMessage(),
                ], 401);
            }

            return back()
                ->withErrors([
                    'email' => $exception->getMessage(),
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
         *
         * The root route is the only confirmed post-login destination here.
         * Replace it with the authorized dashboard route when that route
         * has been verified.
         */
        return redirect()->intended('/');
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

        $authenticationSessionId = $request->session()->get(
            'authentication_session_id',
        );

        if (
            ! is_string($authenticationSessionId)
            || $authenticationSessionId === ''
        ) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'Authentication session not found.',
                ], 401);
            }

            return redirect()
                ->route('authentication.login')
                ->withErrors([
                    'email' => 'Authentication session not found.',
                ]);
        }

        $authenticationSession = $this->sessionService->findActiveById(
            user: $user,
            authenticationSessionId: $authenticationSessionId,
        );

        if ($authenticationSession === null) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'Authentication session not found.',
                ], 401);
            }

            return redirect()
                ->route('authentication.login')
                ->withErrors([
                    'email' => 'Authentication session not found.',
                ]);
        }

        $revoked = $this->sessionService->revoke(
            session: $authenticationSession,
            reason: 'logout',
        );

        if (! $revoked) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'Unable to logout.',
                ], 401);
            }

            return redirect()
                ->route('authentication.login')
                ->withErrors([
                    'email' => 'Unable to logout.',
                ]);
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
