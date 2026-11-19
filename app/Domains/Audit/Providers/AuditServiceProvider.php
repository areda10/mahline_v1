<?php

declare(strict_types=1);

namespace App\Domains\Audit\Providers;

use App\Domains\Audit\Models\AuditEvent;
use App\Domains\Audit\Policies\AuditEventPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

final class AuditServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Gate::policy(AuditEvent::class, AuditEventPolicy::class);
    }
}
