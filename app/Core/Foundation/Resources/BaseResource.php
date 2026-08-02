<?php

declare(strict_types=1);

namespace App\Core\Foundation\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Classe de base de toutes les Resources MAHLINE.
 *
 * Transforme un objet métier en représentation JSON.
 */
abstract class BaseResource extends JsonResource
{
    /**
     * Transforme la ressource en tableau.
     */
    public function toArray(Request $request): array
    {
        return parent::toArray($request);
    }
}
