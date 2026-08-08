<?php

declare(strict_types=1);

namespace App\Core\Database\Macros;

use Illuminate\Database\Schema\Blueprint;

final class ColumnMacros
{
    /**
     * Register MAHLINE standard Blueprint column macros.
     */
    public static function register(): void
    {
        if (! Blueprint::hasMacro('ulidPrimary')) {
            Blueprint::macro(
                'ulidPrimary',
                function (string $column = 'id'): void {
                    /** @var Blueprint $this */
                    $this->ulid($column)->primary();
                }
            );
        }

        if (! Blueprint::hasMacro('status')) {
            Blueprint::macro(
                'status',
                function (
                    string $column = 'status',
                    string $default = 'active',
                ): void {
                    /** @var Blueprint $this */
                    $this->string($column)->default($default);
                }
            );
        }
    }
}