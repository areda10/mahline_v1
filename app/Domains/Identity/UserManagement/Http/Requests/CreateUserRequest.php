<?php

declare(strict_types=1);

namespace App\Domains\Identity\UserManagement\Http\Requests;

use App\Domains\Identity\Users\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

final class CreateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null
            && Gate::forUser($this->user())->allows('create', User::class);
    }

    public function rules(): array
    {
        $actor = $this->user();

        $allowedRoles = $actor->hasRole('super_admin_root')
            ? ['super_admin', 'cooperative_admin', 'user']
            : ['cooperative_admin', 'user'];

        $roleRequiresCooperative = in_array(
            $this->input('role_slug'),
            ['cooperative_admin', 'user'],
            true,
        );

        return [
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'string',
                'email',
                'max:255',
                Rule::unique('users', 'email'),
            ],
            'telephone' => ['required', 'string', 'max:255'],
            'role_slug' => [
                'required',
                'string',
                Rule::in($allowedRoles),
                Rule::exists('roles', 'slug')->where('is_active', true),
            ],
            'cooperative_id' => [
                Rule::requiredIf($roleRequiresCooperative),
                $roleRequiresCooperative ? 'string' : 'prohibited',
                'nullable',
                'size:26',
                Rule::exists('cooperatives', 'id')->whereNull('deleted_at'),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'first_name.required' => 'Le prénom est obligatoire.',
            'last_name.required' => 'Le nom est obligatoire.',
            'email.required' => 'L’adresse e-mail est obligatoire.',
            'email.email' => 'L’adresse e-mail est invalide.',
            'email.unique' => 'Cette adresse e-mail est déjà utilisée.',
            'telephone.required' => 'Le téléphone est obligatoire.',
            'role_slug.required' => 'Le rôle est obligatoire.',
            'role_slug.in' => 'Ce rôle ne peut pas être attribué par votre compte.',
            'role_slug.exists' => 'Ce rôle est indisponible.',
            'cooperative_id.required' => 'La coopérative est obligatoire pour ce rôle.',
            'cooperative_id.exists' => 'La coopérative sélectionnée est invalide.',
            'cooperative_id.prohibited' => 'Ce rôle ne doit pas être associé directement à une coopérative.',
        ];
    }
}
