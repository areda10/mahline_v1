<?php

declare(strict_types=1);

namespace App\Domains\Identity\Customer\Http\Controllers;

use App\Domains\Identity\Customer\Exceptions\CustomerVerificationDeliveryException;
use App\Domains\Identity\Customer\Http\Requests\CustomerRegistrationRequest;
use App\Domains\Identity\Customer\Http\Requests\CustomerResendVerificationRequest;
use App\Domains\Identity\Customer\Services\CustomerRegistrationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use InvalidArgumentException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;

final class CustomerRegistrationController
{
    public function __construct(
        private readonly CustomerRegistrationService $registration,
    ) {}

    public function create(): View
    {
        return view('customer.auth.register');
    }

    public function notice(): View
    {
        return view('customer.auth.verification-notice');
    }

    public function store(CustomerRegistrationRequest $request): RedirectResponse
    {
        try {
            $this->registration->register($request->validated());
        } catch (InvalidArgumentException $exception) {
            return back()
                ->withErrors(['password' => $exception->getMessage()])
                ->withInput($request->safe()->except(['password', 'password_confirmation']));
        } catch (CustomerVerificationDeliveryException) {
            // Keep the public response generic. The pending account can request
            // a fresh verification link without exposing mail infrastructure.
        }

        return redirect()
            ->route('customer.verification.notice')
            ->with('status', 'Si votre inscription peut être finalisée, vous recevrez un e-mail de vérification. Le lien est valable 24 heures.');
    }

    public function verify(Request $request): RedirectResponse
    {
        $verified = $this->registration->verifyEmail(
            (string) $request->query('token', ''),
        );

        if (! $verified) {
            return redirect()
                ->route('customer.verification.notice')
                ->with('error', 'Ce lien est invalide, expiré ou déjà utilisé. Vous pouvez demander un nouveau lien.');
        }

        return redirect()
            ->route('customer.login')
            ->with('status', 'Votre adresse e-mail est vérifiée et votre compte client est activé. Vous pouvez vous connecter.');
    }

    public function resend(CustomerResendVerificationRequest $request): RedirectResponse
    {
        try {
            $this->registration->resendVerification(
                (string) $request->validated('email'),
            );
        } catch (TooManyRequestsHttpException) {
            return back()->withErrors([
                'email' => 'Trop de demandes. Veuillez attendre une heure avant de réessayer.',
            ]);
        } catch (CustomerVerificationDeliveryException) {
            // Do not reveal whether an address belongs to a customer account.
        }

        return redirect()
            ->route('customer.verification.notice')
            ->with('status', 'Si un compte client en attente correspond à cette adresse, un nouveau lien lui sera envoyé.');
    }
}
