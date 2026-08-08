<?php

declare(strict_types=1);

namespace App\Core\Database\Macros;

use Illuminate\Database\Schema\Blueprint;

final class SoftDeleteMacros
{
    /**
     * Register MAHLINE soft-delete Blueprint macros.
     */
    public static function register(): void
    {
        if (! Blueprint::hasMacro('softDeleteColumn')) {
            Blueprint::macro(
                'softDeleteColumn',
                function (string $column = 'deleted_at'): void {
                    /** @var Blueprint $this */

                    $this->softDeletes($column);
                }
            );
        }
    }
}