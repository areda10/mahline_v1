<?php

declare(strict_types=1);

namespace App\Domains\Identity\Customer\Http\Requests;

use App\Domains\Identity\Users\Models\User;
use Illuminate\Foundation\Http\FormRequest;

final class RequestCustomerEmailChange extends FormRequest
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
            'new_email' => ['required', 'string', 'email:rfc', 'max:255'],
            'current_password' => ['required', 'string', 'max:255'],
        ];
    }
}
