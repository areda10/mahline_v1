<?php

declare(strict_types=1);

namespace App\Core\Foundation\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Classe de base de toutes les requêtes HTTP du framework MAHLINE.
 *
 * Centralise les comportements communs des FormRequest.
 */
abstract class BaseRequest extends FormRequest
{
    /**
     * Autorisation par défaut.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Les règles seront définies
     * dans les classes enfants.
     */
    public function rules(): array
    {
        return [];
    }
}
