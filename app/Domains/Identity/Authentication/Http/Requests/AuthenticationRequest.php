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
            'email.string' => 'The email must be a string.',
            'email.email' => 'The email address is invalid.',
            'email.max' => 'The email address may not be greater than 255 characters.',

            'password.required' => 'Password is required.',
            'password.string' => 'The password must be a string.',
        ];
    }

    /**
     * Determine whether the request expects a JSON response.
     *
     * Authentication is exposed as a JSON endpoint.
     */
    public function expectsJson(): bool
    {
        return true;
    }
}