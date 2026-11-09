<?php

declare(strict_types=1);

namespace App\Domains\Identity\UserManagement\Http\Controllers;

use App\Domains\Identity\UserManagement\Http\Requests\AcceptUserInvitationRequest;
use App\Domains\Identity\UserManagement\Services\UserInvitationService;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use InvalidArgumentException;

final class UserInvitationController
{
    public function __construct(
        private readonly UserInvitationService $invitationService,
    ) {}

    public function show(Request $request): View|RedirectResponse
    {
        $token = (string) $request->query('token', '');

        try {
            $this->invitationService->validateInvitation($token);
        } catch (DomainException|InvalidArgumentException $exception) {
            return redirect()
                ->route('authentication.login')
                ->with('error', $exception->getMessage());
        }

        return view('auth.accept-invitation', [
            'token' => $token,
        ]);
    }

    public function accept(AcceptUserInvitationRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        try {
            $this->invitationService->acceptInvitation(
                token: $validated['token'],
                password: $validated['password'],
            );
        } catch (DomainException|InvalidArgumentException $exception) {
            return back()
                ->withErrors([
                    'invitation' => $exception->getMessage(),
                ])
                ->withInput($request->only('token'));
        }

        return redirect()
            ->route('authentication.login')
            ->with(
                'status',
                'Votre compte est activé. Vous pouvez maintenant vous connecter.',
            );
    }
}
