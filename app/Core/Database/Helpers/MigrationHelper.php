<?php

declare(strict_types=1);

namespace App\Core\Database\Helpers;

use Illuminate\Database\Schema\Blueprint;

final class MigrationHelper
{
    /**
     * Add the standard MAHLINE primary key.
     */
    public static function ulidPrimary(
        Blueprint $table,
        string $column = 'id',
    ): void {
        $table->ulidPrimary($column);
    }

    /**
     * Add the standard MAHLINE audit columns.
     */
    public static function auditColumns(
        Blueprint $table,
    ): void {
        $table->auditColumns();
    }

    /**
     * Add the standard MAHLINE soft-delete column.
     */
    public static function softDeleteColumn(
        Blueprint $table,
        string $column = 'deleted_at',
    ): void {
        $table->softDeleteColumn($column);
    }

    /**
     * Add the standard MAHLINE status column.
     */
    public static function status(
        Blueprint $table,
        string $column = 'status',
        string $default = 'active',
    ): void {
        $table->status($column, $default);
    }
}