<?php

declare(strict_types=1);

namespace App\Domains\Identity\Authentication\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class AuthenticationRequest extends FormRequest
{
    /**
     * Authorization is handled by the authentication flow.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Authentication input validation rules.
     */
    public function rules(): array
    {
        return [
            'email' => [
                'required',
                'string',
                'email',
                'max:255',
            ],

            'password' => [
                'required',
                'string',
                'min:8',
            ],
        ];
    }

    /**
     * Validation messages.
     */
    public function messages(): array
    {
        return [
            'email.required' => 'Email is required.',
            'email.email' => 'The email address is invalid.',
            'password.required' => 'Password is required.',
            'password.min' => 'The password must contain at least 8 characters.',
        ];
    }
}