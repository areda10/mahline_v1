<?php

declare(strict_types=1);

namespace App\Domains\Audit\Providers;

use App\Domains\Audit\Contracts\AuditWriter;
use App\Domains\Audit\Models\AuditEvent;
use App\Domains\Audit\Services\AuditWriterService;
use App\Domains\Audit\Policies\AuditEventPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

final class AuditServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(AuditWriter::class, AuditWriterService::class);
    }

    public function boot(): void
    {
        Gate::policy(AuditEvent::class, AuditEventPolicy::class);
    }
}
