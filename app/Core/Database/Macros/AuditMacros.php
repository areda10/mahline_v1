<?php

declare(strict_types=1);

namespace App\Core\Database\Macros;

use Illuminate\Database\Schema\Blueprint;

final class AuditMacros
{
    /**
     * Register MAHLINE audit-related Blueprint macros.
     */
    public static function register(): void
    {
        if (! Blueprint::hasMacro('auditColumns')) {
            Blueprint::macro(
                'auditColumns',
                function (): void {
                    /** @var Blueprint $this */
                    $this->timestamps();
                }
            );
        }
    }
}