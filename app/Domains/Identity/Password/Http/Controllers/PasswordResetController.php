<?php

declare(strict_types=1);

namespace App\Domains\Identity\Password\Http\Controllers;

use App\Domains\Identity\Password\Exceptions\InvalidPasswordResetTokenException;
use App\Domains\Identity\Password\Http\Requests\ForgotPasswordRequest;
use App\Domains\Identity\Password\Http\Requests\ResetPasswordRequest;
use App\Domains\Identity\Password\Services\PasswordResetService;
use App\Domains\Identity\Users\Models\User;
use Illuminate\Http\JsonResponse;

final class PasswordResetController
{
    public function __construct(
        private readonly PasswordResetService $passwordResetService,
    ) {
    }

    public function forgot(
        ForgotPasswordRequest $request,
    ): JsonResponse {
        $user = User::query()
            ->where('email', $request->validated('email'))
            ->first();

        if ($user !== null) {
            $this->passwordResetService->createToken(
                user: $user,
            );
        }

        return response()->json([
            'message' => 'If the email address exists, a password reset link has been sent.',
        ]);
    }

    public function reset(
        ResetPasswordRequest $request,
    ): JsonResponse {
        try {
            $this->passwordResetService->resetPassword(
                token: $request->validated('token'),
                newPassword: $request->validated('password'),
            );
        } catch (InvalidPasswordResetTokenException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
            ], 422);
        }

        return response()->json([
            'message' => 'Password has been reset successfully.',
        ]);
    }
}