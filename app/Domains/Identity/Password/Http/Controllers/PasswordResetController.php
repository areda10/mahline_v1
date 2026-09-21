<?php

declare(strict_types=1);

namespace App\Domains\Identity\Password\Http\Controllers;

use App\Domains\Identity\Password\Http\Requests\ForgotPasswordRequest;
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
}