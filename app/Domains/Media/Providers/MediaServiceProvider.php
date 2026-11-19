<?php

declare(strict_types=1);

namespace App\Domains\Media\Providers;

use App\Domains\Media\Models\Media;
use App\Domains\Media\Policies\MediaPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

final class MediaServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Gate::policy(Media::class, MediaPolicy::class);
    }
}
