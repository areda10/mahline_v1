<?php

declare(strict_types=1);

namespace App\Domains\Media\Providers;

use App\Domains\Media\Contracts\PrivateMediaAttachmentUploader;
use App\Domains\Media\Models\Media;
use App\Domains\Media\Policies\MediaPolicy;
use App\Domains\Media\Services\PrivateMediaAttachmentUploadService;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

final class MediaServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(PrivateMediaAttachmentUploader::class, PrivateMediaAttachmentUploadService::class);
    }

    public function boot(): void
    {
        Gate::policy(Media::class, MediaPolicy::class);
    }
}
