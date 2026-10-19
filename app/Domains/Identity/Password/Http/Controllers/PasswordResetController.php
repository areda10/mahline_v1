<?php

declare(strict_types=1);

namespace App\Domains\Identity\Password\Http\Controllers;

use App\Domains\Identity\Password\Exceptions\InvalidPasswordResetTokenException;
use App\Domains\Identity\Password\Http\Requests\ForgotPasswordRequest;
use App\Domains\Identity\Password\Http\Requests\ResetPasswordRequest;
use App\Domains\Identity\Password\Services\PasswordResetService;
use App\Domains\Identity\Users\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use InvalidArgumentException;

final class PasswordResetController
{
    public function __construct(
        private readonly PasswordResetService $passwordResetService,
    ) {
    }

    public function showForgotForm(): View
    {
        return view('auth.forgot-password');
    }

    public function showResetForm(Request $request): View
    {
        return view('auth.reset-password', [
            'token' => (string) $request->query('token', ''),
            'email' => (string) $request->query('email', ''),
        ]);
    }

    public function forgot(
        ForgotPasswordRequest $request,
    ): JsonResponse|RedirectResponse {
        $user = User::query()
            ->where('email', $request->validated('email'))
            ->first();

        if ($user !== null) {
            $this->passwordResetService->createToken(
                user: $user,
            );
        }

        $message = 'If the email address exists, a password reset link has been sent.';

        if ($this->wantsJson($request)) {
            return response()->json([
                'message' => $message,
            ]);
        }

        return redirect()
            ->route('password.request')
            ->with('status', $message);
    }

    public function reset(
        ResetPasswordRequest $request,
    ): JsonResponse|RedirectResponse {
        try {
            $this->passwordResetService->resetPassword(
                token: $request->validated('token'),
                newPassword: $request->validated('password'),
            );
        } catch (InvalidPasswordResetTokenException $exception) {
            if ($this->wantsJson($request)) {
                return response()->json([
                    'message' => $exception->getMessage(),
                ], 422);
            }

            return back()
                ->withErrors([
                    'token' => $exception->getMessage(),
                ])
                ->withInput($request->only('email', 'token'));
        } catch (InvalidArgumentException $exception) {
            if ($this->wantsJson($request)) {
                return response()->json([
                    'message' => $exception->getMessage(),
                ], 422);
            }

            return back()
                ->withErrors([
                    'password' => $exception->getMessage(),
                ])
                ->withInput($request->only('email', 'token'));
        }

        if ($this->wantsJson($request)) {
            return response()->json([
                'message' => 'Password has been reset successfully.',
            ]);
        }

        return redirect()
            ->route('authentication.login')
            ->with('status', 'Password has been reset successfully.');
    }

    private function wantsJson(Request $request): bool
    {
        return $request->expectsJson()
            || $request->is('api/*');
    }
}