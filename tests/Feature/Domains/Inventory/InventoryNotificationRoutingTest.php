<?php

declare(strict_types=1);

namespace Tests\Feature\Domains\Inventory;

use App\Domains\Cooperatives\Models\Cooperative;
use App\Domains\Identity\Authorization\Models\Role;
use App\Domains\Identity\Enums\UserStatus;
use App\Domains\Identity\Notifications\InventorySecurityReportNotification;
use App\Domains\Identity\Notifications\InventorySecurityReportNotificationDispatcher;
use App\Domains\Identity\Notifications\InventoryStockAlertNotification;
use App\Domains\Identity\Notifications\InventoryStockAlertNotificationDispatcher;
use App\Domains\Identity\Users\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

final class InventoryNotificationRoutingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
    }

    public function test_stock_alerts_reach_only_operational_users_of_the_cooperative_and_assigned_super_admins(): void
    {
        Notification::fake();

        $cooperative = Cooperative::factory()->active()->create();
        $cooperativeAdmin = $this->userWithRole('cooperative_admin', (string) $cooperative->getKey());
        $cooperativeUser = $this->userWithRole('user', (string) $cooperative->getKey());
        $assignedSuperAdmin = $this->userWithRole('super_admin');
        $assignedSuperAdmin->cooperatives()->attach($cooperative->getKey());
        $customer = $this->userWithRole('customer', (string) $cooperative->getKey());
        $root = $this->userWithRole('super_admin_root');
        $inactiveAdmin = $this->userWithRole('cooperative_admin', (string) $cooperative->getKey(), [
            'status' => UserStatus::Inactive,
        ]);
        $unverifiedUser = $this->userWithRole('user', (string) $cooperative->getKey(), [
            'email_verified_at' => null,
        ]);
        $lockedUser = $this->userWithRole('user', (string) $cooperative->getKey(), [
            'emergency_locked_at' => now(),
        ]);

        app(InventoryStockAlertNotificationDispatcher::class)->dispatch(
            'inventory.stock_alert.triggered',
            ['cooperative_id' => (string) $cooperative->getKey(), 'stock_id' => 'stock-1'],
        );

        Notification::assertSentTo(
            $cooperativeAdmin,
            InventoryStockAlertNotification::class,
            static fn (InventoryStockAlertNotification $notification, array $channels): bool => $channels === ['database']
                && $notification->toDatabase($cooperativeAdmin)['title'] === 'Alerte de stock'
                && $notification->toDatabase($cooperativeAdmin)['url'] === route('backoffice.inventory.index'),
        );
        Notification::assertSentTo($cooperativeUser, InventoryStockAlertNotification::class);
        Notification::assertSentTo($assignedSuperAdmin, InventoryStockAlertNotification::class);
        Notification::assertNotSentTo($customer, InventoryStockAlertNotification::class);
        Notification::assertNotSentTo($root, InventoryStockAlertNotification::class);
        Notification::assertNotSentTo($inactiveAdmin, InventoryStockAlertNotification::class);
        Notification::assertNotSentTo($unverifiedUser, InventoryStockAlertNotification::class);
        Notification::assertNotSentTo($lockedUser, InventoryStockAlertNotification::class);
    }

    public function test_inventory_security_reports_reach_root_cooperative_admin_and_assigned_super_admin_only(): void
    {
        Notification::fake();

        $cooperative = Cooperative::factory()->active()->create();
        $cooperativeAdmin = $this->userWithRole('cooperative_admin', (string) $cooperative->getKey());
        $cooperativeUser = $this->userWithRole('user', (string) $cooperative->getKey());
        $assignedSuperAdmin = $this->userWithRole('super_admin');
        $assignedSuperAdmin->cooperatives()->attach($cooperative->getKey());
        $customer = $this->userWithRole('customer', (string) $cooperative->getKey());
        $root = $this->userWithRole('super_admin_root');
        $inactiveAdmin = $this->userWithRole('cooperative_admin', (string) $cooperative->getKey(), [
            'status' => UserStatus::Inactive,
        ]);
        $unverifiedAdmin = $this->userWithRole('cooperative_admin', (string) $cooperative->getKey(), [
            'email_verified_at' => null,
        ]);
        $lockedAdmin = $this->userWithRole('cooperative_admin', (string) $cooperative->getKey(), [
            'emergency_locked_at' => now(),
        ]);

        app(InventorySecurityReportNotificationDispatcher::class)->dispatch(
            'inventory.security_report.created',
            ['cooperative_id' => (string) $cooperative->getKey(), 'report_id' => 'report-1'],
        );

        Notification::assertSentTo($root, InventorySecurityReportNotification::class, static fn (InventorySecurityReportNotification $notification, array $channels): bool => $channels === ['database']);
        Notification::assertSentTo(
            $cooperativeAdmin,
            InventorySecurityReportNotification::class,
            static fn (InventorySecurityReportNotification $notification): bool => $notification->toDatabase($cooperativeAdmin)['title'] === 'Nouveau signalement de sécurité'
                && $notification->toDatabase($cooperativeAdmin)['url'] === route('inventory.security-reports.index'),
        );
        Notification::assertSentTo($assignedSuperAdmin, InventorySecurityReportNotification::class);
        Notification::assertNotSentTo($cooperativeUser, InventorySecurityReportNotification::class);
        Notification::assertNotSentTo($customer, InventorySecurityReportNotification::class);
        Notification::assertNotSentTo($inactiveAdmin, InventorySecurityReportNotification::class);
        Notification::assertNotSentTo($unverifiedAdmin, InventorySecurityReportNotification::class);
        Notification::assertNotSentTo($lockedAdmin, InventorySecurityReportNotification::class);
    }

    private function userWithRole(
        string $slug,
        ?string $cooperativeId = null,
        array $overrides = [],
    ): User {
        $role = Role::query()->where('slug', $slug)->firstOrFail();

        $user = User::factory()->create([
            'status' => UserStatus::Active,
            'email_verified_at' => now(),
            'cooperative_id' => $cooperativeId,
            ...$overrides,
        ]);
        $user->roles()->attach($role->getKey());

        return $user;
    }
}
