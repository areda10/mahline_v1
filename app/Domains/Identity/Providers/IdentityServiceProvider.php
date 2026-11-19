<?php

declare(strict_types=1);

namespace App\Domains\Identity\Providers;

use App\Domains\Identity\Authentication\Models\PersonalAccessToken;
use App\Domains\Identity\Authentication\Workflows\AuthenticationWorkflowHook;
use App\Domains\Identity\Authentication\Workflows\RecordNewDeviceAuthenticationWorkflowHook;
use App\Domains\Identity\UserManagement\Contracts\SuperAdminCooperativeAssignmentService;
use App\Domains\Identity\UserManagement\Services\UserManagementService;
use App\Domains\Identity\Users\Models\User;
use App\Domains\Identity\Users\Observers\UserObserver;
use Illuminate\Support\ServiceProvider;
use Laravel\Sanctum\Sanctum;

final class IdentityServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(
            SuperAdminCooperativeAssignmentService::class,
            UserManagementService::class,
        );

        $this->app->bind(
            AuthenticationWorkflowHook::class,
            RecordNewDeviceAuthenticationWorkflowHook::class,
        );
    }

    public function boot(): void
    {
        User::observe(UserObserver::class);

        Sanctum::usePersonalAccessTokenModel(PersonalAccessToken::class);
    }
}
