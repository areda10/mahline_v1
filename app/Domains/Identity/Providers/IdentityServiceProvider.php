<?php

declare(strict_types=1);

namespace App\Domains\Identity\Providers;

use App\Domains\Cooperatives\Events\CooperativeCreatedV1;
use App\Domains\Cooperatives\Contracts\CooperativeMemberAccessRevoker;
use App\Domains\Identity\Authentication\Services\CooperativeMemberAccessRevokerService;
use App\Domains\Identity\Notifications\InventoryStockAlertNotificationDispatcher;
use App\Domains\Identity\Notifications\InventorySecurityReportNotificationDispatcher;
use App\Domains\Identity\Notifications\Listeners\DispatchInventorySecurityReportNotification;
use App\Domains\Identity\Notifications\Listeners\DispatchInventoryStockAlert;
use App\Domains\Inventory\Events\InventoryDomainEvent;
use App\Domains\Identity\Authentication\Models\PersonalAccessToken;
use App\Domains\Identity\Authorization\Services\SuperAdminCooperativeAssignmentReaderService;
use App\Domains\Identity\Authorization\Services\IdentityActorAuthorizer;
use App\Domains\Identity\Authorization\Services\IdentityActorContextProvider;
use App\Domains\Identity\Authentication\Workflows\AuthenticationWorkflowHook;
use App\Domains\Identity\Authentication\Workflows\RecordNewDeviceAuthenticationWorkflowHook;
use App\Domains\Identity\UserManagement\Contracts\SuperAdminCooperativeAssignmentService;
use App\Domains\Identity\UserManagement\Listeners\AssignCreatorToNewCooperative;
use App\Domains\Identity\UserManagement\Services\UserManagementService;
use App\Domains\Identity\Users\Models\User;
use App\Domains\Identity\Users\Services\IdentityUserAddressOwnerGateway;
use App\Domains\Localization\Contracts\UserAddressOwnerGateway;
use App\Shared\Contracts\ActorAuthorizer;
use App\Shared\Contracts\SuperAdminCooperativeAssignmentReader;
use App\Shared\Contracts\ActorContextProvider;
use App\Shared\Contracts\InventoryAlertNotificationDispatcher;
use App\Shared\Contracts\InventorySecurityReportNotificationDispatcher as InventorySecurityReportNotificationDispatcherContract;
use App\Domains\Identity\Users\Observers\UserObserver;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Laravel\Sanctum\Sanctum;

final class IdentityServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(SuperAdminCooperativeAssignmentReader::class, SuperAdminCooperativeAssignmentReaderService::class);
        $this->app->bind(CooperativeMemberAccessRevoker::class, CooperativeMemberAccessRevokerService::class);
        $this->app->bind(UserAddressOwnerGateway::class, IdentityUserAddressOwnerGateway::class);
        $this->app->bind(ActorContextProvider::class, IdentityActorContextProvider::class);
        $this->app->bind(ActorAuthorizer::class, IdentityActorAuthorizer::class);
        $this->app->bind(InventoryAlertNotificationDispatcher::class, InventoryStockAlertNotificationDispatcher::class);
        $this->app->bind(InventorySecurityReportNotificationDispatcherContract::class, InventorySecurityReportNotificationDispatcher::class);

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

        Event::listen(CooperativeCreatedV1::class, AssignCreatorToNewCooperative::class);
        Event::listen(InventoryDomainEvent::class, DispatchInventoryStockAlert::class);
        Event::listen(InventoryDomainEvent::class, DispatchInventorySecurityReportNotification::class);

        Sanctum::usePersonalAccessTokenModel(PersonalAccessToken::class);
    }
}
