<?php

declare(strict_types=1);

namespace App\Domains\Identity\Authentication\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class StoreApiTokenRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:1', 'max:255', 'not_regex:/^\\s*$/'],
        ];
    }
}
