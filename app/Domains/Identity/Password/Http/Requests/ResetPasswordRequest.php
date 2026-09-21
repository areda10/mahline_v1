<?php

declare(strict_types=1);

namespace App\Domains\Identity\Password\Http\Requests;

use App\Domains\Identity\Password\Rules\PasswordPolicy;
use App\Domains\Identity\Password\Rules\ValidPassword;
use Illuminate\Foundation\Http\FormRequest;

final class ResetPasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'token' => [
                'required',
                'string',
            ],
            'password' => [
                'required',
                'string',
                new ValidPassword(),
            ],
            'password_confirmation' => [
                'required',
                'string',
                'same:password',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'token.required' => 'Reset token is required.',
            'token.string' => 'The reset token must be a string.',
            'password.required' => 'Password is required.',
            'password.string' => 'The password must be a string.',
            'password_confirmation.required' => 'Password confirmation is required.',
            'password_confirmation.string' => 'Password confirmation must be a string.',
            'password_confirmation.same' => 'Password confirmation does not match.',
        ];
    }

    public function expectsJson(): bool
    {
        return true;
    }

    // protected function prepareForValidation(): void
    // {
    //     if (! $this->has('password')) {
    //     return;
    //     }

    //     try {
    //         PasswordPolicy::validate(
    //             (string) $this->input('password'),
    //         );
    //     } catch (\InvalidArgumentException $exception) {
    //         $this->getValidatorInstance()
    //             ->errors()
    //             ->add('password', $exception->getMessage());
    //     }
    // }
}