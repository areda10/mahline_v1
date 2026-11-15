<?php

declare(strict_types=1);

namespace App\Domains\Identity\UserManagement\Http\Requests;

use App\Domains\Identity\Password\Rules\ValidPassword;
use Illuminate\Foundation\Http\FormRequest;

final class AcceptUserInvitationRequest extends FormRequest
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
                'confirmed',
                new ValidPassword,
            ],
            'password_confirmation' => [
                'required',
                'string',
            ],
        ];
    }
}
