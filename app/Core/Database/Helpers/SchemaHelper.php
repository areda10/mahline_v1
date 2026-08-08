<?php

declare(strict_types=1);

namespace App\Core\Database\Helpers;

use Illuminate\Support\Facades\Schema;

final class SchemaHelper
{
    /**
     * Determine whether a table contains the standard MAHLINE
     * audit columns.
     */
    public static function hasAuditColumns(
        string $table,
    ): bool {
        return Schema::hasColumns(
            $table,
            [
                'created_at',
                'updated_at',
            ]
        );
    }
}