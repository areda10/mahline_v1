<?php

declare(strict_types=1);

namespace Tests\Feature\Domains\Inventory;

use App\Domains\Cooperatives\Models\Cooperative;
use App\Domains\Identity\Authorization\Models\Role;
use App\Domains\Identity\Users\Models\User;
use App\Domains\Inventory\Contracts\InventorySecurityReportManager;
use App\Domains\Inventory\Enums\InventorySecurityReportUrgency;
use App\Domains\Inventory\Models\InventorySecurityReport;
use App\Domains\Inventory\Models\InventorySecurityReportEvent;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

final class InventorySecurityReportServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([RoleSeeder::class, PermissionSeeder::class, RolePermissionSeeder::class]);
    }

    public function test_urgent_report_is_visible_to_its_reporter_and_escalates_after_two_hours_without_releasing_holds(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-21 10:00:00'));
        $cooperative = Cooperative::factory()->create();
        $reporter = $this->userWithRole('user', [
            'cooperative_id' => $cooperative->getKey(),
        ]);

        $manager = app(InventorySecurityReportManager::class);
        $reportId = $manager->reportRisk(
            (string) $cooperative->getKey(),
            null,
            'product_safety',
            'Un risque potentiel a été signalé sur un produit.',
            InventorySecurityReportUrgency::URGENT,
            (string) $reporter->getKey(),
        );

        $report = $manager->visibleReport($reportId, (string) $reporter->getKey());
        $this->assertSame('open', $report->status);
        $this->assertSame('urgent', $report->urgency);
        $this->assertSame('2026-11-21 12:00:00', $report->response_due_at->format('Y-m-d H:i:s'));

        Carbon::setTestNow(Carbon::parse('2026-11-21 12:01:00'));
        $this->artisan('inventory:escalate-security-reports')->assertSuccessful();

        $report = InventorySecurityReport::query()->findOrFail($reportId);
        $this->assertNotNull($report->escalated_at);
        $this->assertSame('open', $report->status);
        $this->assertDatabaseHas('inventory_security_report_events', [
            'report_id' => $reportId,
            'event_type' => 'escalated',
        ]);
        $this->assertSame(2, InventorySecurityReportEvent::query()->where('report_id', $reportId)->count());

        Carbon::setTestNow();
    }

    private function userWithRole(string $roleSlug, array $attributes = []): User
    {
        $role = Role::query()->where('slug', $roleSlug)->firstOrFail();

        return User::factory()->create(array_merge([
            'role_id' => $role->getKey(),
            'status' => 'active',
            'email_verified_at' => now(),
        ], $attributes));
    }
}
