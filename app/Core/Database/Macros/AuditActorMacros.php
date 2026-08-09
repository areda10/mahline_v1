<?php

declare(strict_types=1);

namespace App\Core\Database\Macros;

use Illuminate\Database\Schema\Blueprint;

final class AuditActorMacros
{
    /**
     * Register audit actor columns on Blueprint.
     */
    public static function register(): void
    {
        if (! Blueprint::hasMacro('auditActorColumns')) {
            Blueprint::macro(
                'auditActorColumns',
                function (): void {
                    /** @var Blueprint $this */

                    $this->char('created_by', 26)->nullable();
                    $this->char('updated_by', 26)->nullable();
                    $this->char('deleted_by', 26)->nullable();
                }
            );
        }
    }
}
