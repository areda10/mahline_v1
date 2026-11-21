<?php

declare(strict_types=1);

namespace App\Domains\Inventory\Services\Security;

use App\Core\Foundation\Services\BaseService;
use App\Domains\Audit\Contracts\AuditWriter;
use App\Domains\Inventory\Contracts\InventorySecurityReportManager;
use App\Domains\Inventory\Enums\InventorySecurityReportUrgency;
use App\Domains\Inventory\Models\InventoryOutboxMessage;
use App\Domains\Inventory\Models\InventorySecurityReport;
use App\Domains\Inventory\Models\InventorySecurityReportAttachment;
use App\Domains\Inventory\Models\InventorySecurityReportEvent;
use App\Domains\Media\Contracts\PrivateMediaAttachmentUploader;
use App\Shared\Contracts\OrderSafetyHoldManager;
use App\Shared\Contracts\ActorContextProvider;
use App\Shared\Contracts\SuperAdminCooperativeAssignmentReader;
use App\Shared\DTOs\ActorContext;
use App\Shared\DTOs\AuditEntryDTO;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

final class InventorySecurityReportService extends BaseService implements InventorySecurityReportManager
{
    public function __construct(
        private readonly ActorContextProvider $actors,
        private readonly SuperAdminCooperativeAssignmentReader $assignments,
        private readonly AuditWriter $audit,
        private readonly OrderSafetyHoldManager $safetyHolds,
        private readonly PrivateMediaAttachmentUploader $attachments,
    ) {}

    public function reportRisk(
        string $cooperativeId,
        ?string $cooperativeOrderId,
        string $category,
        string $description,
        InventorySecurityReportUrgency $urgency,
        string $actorId,
        array $evidenceFiles = [],
    ): string {
        foreach ($evidenceFiles as $file) {
            if (! $file instanceof UploadedFile) {
                throw new InvalidArgumentException('Security report evidence must contain uploaded files.');
            }
        }

        if ($cooperativeOrderId !== null && ! Str::isUlid($cooperativeOrderId)) {
            throw new InvalidArgumentException('The cooperative order reference must be a valid ULID.');
        }

        $actor = $this->actors->forUserId($actorId);
        if (! $actor->authorized || ! $actor->hasPermission('inventory.security_reports.create')) {
            throw new AuthorizationException('The actor cannot create an Inventory security report.');
        }
        $this->assertScope($actor, $cooperativeId);
        if (trim($category) === '' || trim($description) === '') {
            throw new InvalidArgumentException('A security report category and description are required.');
        }

        return DB::transaction(function () use (
            $cooperativeId,
            $cooperativeOrderId,
            $category,
            $description,
            $urgency,
            $actor,
            $evidenceFiles,
        ): string {
            if ($cooperativeOrderId !== null) {
                $this->safetyHolds->assertCooperativeOrderScope($cooperativeOrderId, $cooperativeId);
            }

            $report = InventorySecurityReport::query()->create([
                'cooperative_id' => $cooperativeId,
                'cooperative_order_id' => $cooperativeOrderId,
                'reporter_id' => $actor->id,
                'reporter_role' => $this->actorRole($actor),
                'category' => trim($category),
                'description' => trim($description),
                'urgency' => $urgency->value,
                'status' => 'draft',
                'response_due_at' => $urgency === InventorySecurityReportUrgency::URGENT ? now()->addHours(2) : null,
            ]);

            foreach ($evidenceFiles as $file) {
                $mediaId = $this->attachments->upload($actor->id, $report, $file);
                InventorySecurityReportAttachment::query()->create([
                    'report_id' => (string) $report->getKey(),
                    'media_id' => $mediaId,
                    'uploaded_by' => $actor->id,
                    'attached_at' => now(),
                ]);
            }

            $report->status = 'open';
            $report->save();

            $this->recordEvent($report, 'created', null, 'open', null, $actor, [
                'urgency' => $urgency->value,
                'cooperative_order_id' => $cooperativeOrderId,
                'attachment_count' => count($evidenceFiles),
            ]);

            $this->audit->record(new AuditEntryDTO(
                eventKey: 'inventory.security_report.created',
                action: 'create',
                actorType: $actor->actorType,
                actorId: $actor->id,
                cooperativeId: $cooperativeId,
                subjectType: 'inventory_security_report',
                subjectId: (string) $report->getKey(),
                newValues: [
                    'category' => trim($category),
                    'urgency' => $urgency->value,
                    'cooperative_order_id' => $cooperativeOrderId,
                    'attachment_count' => count($evidenceFiles),
                ],
            ));

            $this->outbox('inventory.security_report.created', [
                'report_id' => (string) $report->getKey(),
                'cooperative_id' => $cooperativeId,
                'cooperative_order_id' => $cooperativeOrderId,
                'urgency' => $urgency->value,
            ]);

            if ($urgency === InventorySecurityReportUrgency::URGENT && $cooperativeOrderId !== null) {
                $holdId = $this->safetyHolds->raiseHold(
                    $cooperativeOrderId,
                    'inventory_security_report',
                    (string) $report->getKey(),
                    'Signalement de sécurité Inventory urgent : examen humain obligatoire avant expédition.',
                    $actor->id,
                    $actor->actorType,
                    $cooperativeId,
                );

                if ($holdId !== '') {
                    $this->recordEvent($report, 'safety_hold_raised', 'open', 'open', null, $actor, [
                        'order_safety_hold_id' => $holdId,
                    ]);
                }
            }

            return (string) $report->getKey();
        }, 3);
    }

    public function visibleReports(string $actorId): Collection
    {
        $actor = $this->actors->forUserId($actorId);
        if (! $actor->authorized) {
            throw new AuthorizationException('The actor cannot list Inventory security reports.');
        }

        $query = InventorySecurityReport::query()->latest('created_at');
        if ($actor->hasRole('super_admin_root') && $actor->hasPermission('inventory.security_reports.view')) {
            return $query->get();
        }

        if ($actor->hasRole('super_admin') && $actor->hasPermission('inventory.security_reports.view')) {
            return $query->whereIn('cooperative_id', $this->assignments->cooperativeIdsFor($actor->id))->get();
        }

        if ($actor->hasRole('cooperative_admin')
            && $actor->hasPermission('inventory.security_reports.view')
            && $actor->cooperativeId !== null) {
            return $query->where('cooperative_id', $actor->cooperativeId)->get();
        }

        if ($actor->hasRole('user') && $actor->hasPermission('inventory.security_reports.view_own')) {
            return $query->where('reporter_id', $actor->id)
                ->where('cooperative_id', $actor->cooperativeId)
                ->get();
        }

        throw new AuthorizationException('The actor cannot list Inventory security reports.');
    }

    public function visibleReport(string $reportId, string $actorId): InventorySecurityReport
    {
        $report = InventorySecurityReport::query()
            ->with(['events', 'attachments'])
            ->findOrFail($reportId);
        $actor = $this->actors->forUserId($actorId);

        if (! $actor->authorized) {
            throw new AuthorizationException('The actor cannot view this Inventory security report.');
        }

        if ($actor->hasRole('user')
            && $actor->hasPermission('inventory.security_reports.view_own')
            && (string) $report->reporter_id === $actor->id
            && $actor->cooperativeId === (string) $report->cooperative_id) {
            return $report;
        }

        $this->authorizeManager($actor, (string) $report->cooperative_id, 'inventory.security_reports.view');
        if (! $actor->hasRole('super_admin_root')) {
            $report->setRelation('attachments', collect());
        }

        return $report;
    }

    public function close(string $reportId, string $reason, string $actorId): void
    {
        $this->transition($reportId, $reason, $actorId, false);
    }

    public function reopen(string $reportId, string $reason, string $actorId): void
    {
        $this->transition($reportId, $reason, $actorId, true);
    }

    private function transition(string $reportId, string $reason, string $actorId, bool $reopen): void
    {
        if (trim($reason) === '') {
            throw new InvalidArgumentException('A reason is required for a security report status change.');
        }

        $initial = InventorySecurityReport::query()->findOrFail($reportId);
        $actor = $this->actors->forUserId($actorId);
        $permission = $reopen ? 'inventory.security_reports.reopen' : 'inventory.security_reports.close';
        $this->authorizeManager($actor, (string) $initial->cooperative_id, $permission);

        DB::transaction(function () use ($reportId, $reason, $actor, $reopen): void {
            $report = InventorySecurityReport::query()->whereKey($reportId)->lockForUpdate()->firstOrFail();
            $oldStatus = (string) $report->status;

            if ($reopen) {
                $this->safetyHolds->assertSecurityReportCanReopen(
                    $report->cooperative_order_id === null ? null : (string) $report->cooperative_order_id,
                );
            }

            if ($reopen) {
                if ($oldStatus !== 'closed') {
                    throw new InvalidArgumentException('Only a closed security report can be reopened.');
                }
                $report->status = 'reopened';
                $report->reopened_by = $actor->id;
                $report->reopened_at = now();
                $report->reopen_reason = trim($reason);
            } else {
                if (! in_array($oldStatus, ['open', 'reopened', 'under_review'], true)) {
                    throw new InvalidArgumentException('This security report is already closed.');
                }
                $report->status = 'closed';
                $report->closed_by = $actor->id;
                $report->closed_at = now();
                $report->closure_reason = trim($reason);
            }
            $report->save();

            $eventType = $reopen ? 'reopened' : 'closed';
            $this->recordEvent($report, $eventType, $oldStatus, (string) $report->status, $reason, $actor);
            $this->audit->record(new AuditEntryDTO(
                eventKey: 'inventory.security_report.'.$eventType,
                action: $eventType,
                actorType: $actor->actorType,
                actorId: $actor->id,
                cooperativeId: (string) $report->cooperative_id,
                subjectType: 'inventory_security_report',
                subjectId: $reportId,
                oldValues: ['status' => $oldStatus],
                newValues: ['status' => $report->status, 'reason' => trim($reason)],
            ));
            $this->outbox('inventory.security_report.'.$eventType, [
                'report_id' => $reportId,
                'cooperative_id' => (string) $report->cooperative_id,
                'status' => $report->status,
            ]);
        }, 3);
    }

    private function authorizeManager(ActorContext $actor, string $cooperativeId, string $permission): void
    {
        if (! $actor->authorized || ! $actor->hasPermission($permission)) {
            throw new AuthorizationException('The actor does not have the required security report permission.');
        }
        $this->assertScope($actor, $cooperativeId);
    }

    private function assertScope(ActorContext $actor, string $cooperativeId): void
    {
        if ($actor->hasRole('super_admin_root')) {
            return;
        }
        if ($actor->hasRole('cooperative_admin') && $actor->cooperativeId === $cooperativeId) {
            return;
        }
        if ($actor->hasRole('super_admin') && $this->assignments->isAssigned($actor->id, $cooperativeId)) {
            return;
        }
        if ($actor->hasRole('user') && $actor->cooperativeId === $cooperativeId) {
            return;
        }
        throw new AuthorizationException('The actor is outside the cooperative scope.');
    }

    private function actorRole(ActorContext $actor): string
    {
        foreach (['super_admin_root', 'super_admin', 'cooperative_admin', 'user'] as $role) {
            if ($actor->hasRole($role)) {
                return $role;
            }
        }

        return 'system';
    }

    private function recordEvent(
        InventorySecurityReport $report,
        string $eventType,
        ?string $oldStatus,
        ?string $newStatus,
        ?string $reason,
        ActorContext $actor,
        array $metadata = [],
    ): void {
        InventorySecurityReportEvent::query()->create([
            'report_id' => (string) $report->getKey(),
            'event_type' => $eventType,
            'old_status' => $oldStatus,
            'new_status' => $newStatus,
            'reason' => $reason,
            'actor_id' => $actor->id,
            'actor_type' => $actor->actorType,
            'metadata' => $metadata,
            'occurred_at' => now(),
        ]);
    }

    private function outbox(string $eventKey, array $payload): void
    {
        InventoryOutboxMessage::query()->create([
            'event_key' => $eventKey,
            'payload' => $payload,
            'available_at' => now(),
        ]);
    }
}
