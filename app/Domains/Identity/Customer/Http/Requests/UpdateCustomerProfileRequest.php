<?php

declare(strict_types=1);

namespace App\Domains\Identity\Customer\Http\Requests;

use App\Domains\Identity\Users\Models\User;
use Illuminate\Foundation\Http\FormRequest;

final class UpdateCustomerProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user instanceof User
            && $user->hasRole('customer')
            && ! $user->hasAnyRole([
                'super_admin_root',
                'super_admin',
                'cooperative_admin',
                'user',
            ]);
    }

    public function rules(): array
    {
        return [
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'telephone' => [
                'required',
                'string',
                'max:30',
                'regex:/^\\+?[0-9][0-9\\s().-]{6,24}$/',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'telephone.regex' => 'Veuillez saisir un numéro de téléphone valide.',
        ];
    }
}
