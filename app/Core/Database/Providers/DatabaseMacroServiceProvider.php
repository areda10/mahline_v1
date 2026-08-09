<?php

declare(strict_types=1);

namespace App\Core\Database\Providers;

use App\Core\Database\Macros\AuditActorMacros;
use App\Core\Database\Macros\AuditMacros;
use App\Core\Database\Macros\BlueprintMacros;
use App\Core\Database\Macros\ColumnMacros;
use App\Core\Database\Macros\ForeignKeyMacros;
use App\Core\Database\Macros\IndexMacros;
use App\Core\Database\Macros\SoftDeleteMacros;
use Illuminate\Support\ServiceProvider;

final class DatabaseMacroServiceProvider extends ServiceProvider
{
    /**
     * Register database macros.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap database macros.
     */
    public function boot(): void
    {
        ColumnMacros::register();
        AuditMacros::register();
        AuditActorMacros::register();
        ForeignKeyMacros::register();
        IndexMacros::register();
        SoftDeleteMacros::register();
        BlueprintMacros::register();
    }
}