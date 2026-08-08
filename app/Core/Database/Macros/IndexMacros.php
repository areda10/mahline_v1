<?php

declare(strict_types=1);

namespace App\Core\Database\Macros;

use Illuminate\Database\Schema\Blueprint;

final class IndexMacros
{
    /**
     * Register MAHLINE index-related Blueprint macros.
     */
    public static function register(): void
    {
        if (! Blueprint::hasMacro('indexName')) {
            Blueprint::macro(
                'indexName',
                function (
                    string $column,
                    ?string $name = null,
                ): void {
                    /** @var Blueprint $this */

                    $this->index(
                        $column,
                        $name
                    );
                }
            );
        }

        if (! Blueprint::hasMacro('uniqueName')) {
            Blueprint::macro(
                'uniqueName',
                function (
                    string $column,
                    ?string $name = null,
                ): void {
                    /** @var Blueprint $this */

                    $this->unique(
                        $column,
                        $name
                    );
                }
            );
        }

        if (! Blueprint::hasMacro('indexColumns')) {
            Blueprint::macro(
                'indexColumns',
                function (
                    array $columns,
                    ?string $name = null,
                ): void {
                    /** @var Blueprint $this */

                    $this->index(
                        $columns,
                        $name
                    );
                }
            );
        }

        if (! Blueprint::hasMacro('uniqueColumns')) {
            Blueprint::macro(
                'uniqueColumns',
                function (
                    array $columns,
                    ?string $name = null,
                ): void {
                    /** @var Blueprint $this */

                    $this->unique(
                        $columns,
                        $name
                    );
                }
            );
        }
    }
}