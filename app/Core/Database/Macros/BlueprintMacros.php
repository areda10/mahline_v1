<?php

declare(strict_types=1);

namespace App\Core\Database\Macros;

final class BlueprintMacros
{
    /**
     * Register all MAHLINE Blueprint macros.
     */
    public static function register(): void
    {
        ColumnMacros::register();
        AuditMacros::register();
        ForeignKeyMacros::register();
        IndexMacros::register();
        SoftDeleteMacros::register();
    }
}