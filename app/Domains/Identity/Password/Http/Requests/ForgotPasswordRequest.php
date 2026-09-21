<?php

declare(strict_types=1);

namespace App\Domains\Identity\Password\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class ForgotPasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'email' => [
                'required',
                'string',
                'email',
                'max:255',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'email.required' => 'Email is required.',
            'email.string' => 'The email must be a string.',
            'email.email' => 'The email address is invalid.',
            'email.max' => 'The email address may not be greater than 255 characters.',
        ];
    }

    public function expectsJson(): bool
    {
        return true;
    }
}