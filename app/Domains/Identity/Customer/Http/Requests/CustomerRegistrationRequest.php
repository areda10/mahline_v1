<?php

declare(strict_types=1);

namespace App\Domains\Identity\Customer\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class CustomerRegistrationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'string', 'email', 'max:254'],
            'telephone' => [
                'required',
                'string',
                'max:30',
                'regex:/^\+?[0-9][0-9\s().-]{6,24}$/',
            ],
            'password' => [
                'required',
                'string',
                'min:12',
                'max:128',
                'regex:/\p{Lu}/u',
                'regex:/\p{Ll}/u',
                'regex:/\p{N}/u',
                'regex:/[^\p{L}\p{N}\p{Z}\s]/u',
                'confirmed',
            ],
            'password_confirmation' => ['required', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'telephone.regex' => 'Veuillez saisir un numéro de téléphone valide.',
            'password.regex' => 'Le mot de passe doit contenir une majuscule, une minuscule, un chiffre et un caractère spécial.',
            'password.confirmed' => 'La confirmation du mot de passe ne correspond pas.',
        ];
    }
}
