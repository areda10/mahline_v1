<?php

declare(strict_types=1);

namespace App\Domains\Orders\Services;

use App\Domains\Identity\Users\Models\User;
use App\Domains\Orders\Enums\CooperativeOrderStatus;
use App\Domains\Orders\Models\CooperativeOrder;
use App\Domains\Orders\Models\OrderStatusHistory;
use App\Domains\Orders\Notifications\CooperativeOrderWorkflowNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

final class CooperativeOrderEscalationService
{
    /**
     * Escalate overdue cooperative responses without changing the suborder status.
     * The cooperative has 12 hours; its assigned super-admin is alerted at that
     * deadline and may decide until a decision is recorded. Root is alerted at H+24.
     */
    public function escalateOverdue(): int
    {
        $processed = 0;

        CooperativeOrder::query()
            ->where('status', CooperativeOrderStatus::AWAITING_COOPERATIVE->value)
            ->where(function ($query): void {
                $query->where(function ($deadline): void {
                    $deadline->whereNotNull('response_deadline_at')
                        ->where('response_deadline_at', '<=', now())
                        ->whereNull('super_admin_notified_at');
                })->orWhere(function ($root): void {
                    $root->where('created_at', '<=', now()->subHours(24))
                        ->whereNull('root_escalated_at');
                });
            })
            ->select('id')
            ->chunkById(100, function ($rows) use (&$processed): void {
                foreach ($rows as $row) {
                    if ($this->escalateOne((string) $row->getKey())) {
                        $processed++;
                    }
                }
            });

        return $processed;
    }

    private function escalateOne(string $cooperativeOrderId): bool
    {
        return DB::transaction(function () use ($cooperativeOrderId): bool {
            $suborder = CooperativeOrder::query()
                ->whereKey($cooperativeOrderId)
                ->lockForUpdate()
                ->with(['order', 'cooperative'])
                ->first();

            if ($suborder === null || $suborder->status !== CooperativeOrderStatus::AWAITING_COOPERATIVE) {
                return false;
            }

            $changed = false;

            if ($suborder->response_deadline_at !== null
                && $suborder->response_deadline_at->lessThanOrEqualTo(now())
                && $suborder->super_admin_notified_at === null) {
                $recipients = User::query()
                    ->whereHas('roles', static fn ($query) => $query->where('slug', 'super_admin'))
                    ->whereHas('cooperatives', static fn ($query) => $query->whereKey($suborder->cooperative_id))
                    ->get();

                Notification::send($recipients, new CooperativeOrderWorkflowNotification(
                    $suborder,
                    'order.cooperative_response_overdue',
                    'Réponse coopérative en retard',
                    'La coopérative n’a pas répondu à l’échéance convenue (12 heures par défaut, prolongation possible jusqu’à 24 heures). Vous pouvez confirmer ou refuser cette sous-commande avec un motif obligatoire tant qu’aucune décision n’est enregistrée.',
                ));

                if ($suborder->response_overdue_at === null) {
                    $suborder->response_overdue_at = now();
                }
                $suborder->super_admin_notified_at = now();
                $suborder->forceFill(['updated_by' => null])->save();

                OrderStatusHistory::query()->create([
                    'subject_type' => 'cooperative_order',
                    'subject_id' => (string) $suborder->getKey(),
                    'from_status' => CooperativeOrderStatus::AWAITING_COOPERATIVE->value,
                    'to_status' => CooperativeOrderStatus::AWAITING_COOPERATIVE->value,
                    'actor_id' => null,
                    'actor_role' => 'system',
                    'reason' => 'Délai de réponse de la coopérative dépassé : notification du super_admin affecté.',
                    'metadata' => [
                        'action' => 'super_admin_escalation',
                        'recipient_count' => $recipients->count(),
                        'deadline_at' => $suborder->response_deadline_at?->toIso8601String(),
                    ],
                    'occurred_at' => now(),
                ]);

                $changed = true;
            }

            if ($suborder->created_at !== null
                && $suborder->created_at->copy()->addHours(24)->lessThanOrEqualTo(now())
                && $suborder->root_escalated_at === null) {
                $recipients = User::query()
                    ->whereHas('roles', static fn ($query) => $query->where('slug', 'super_admin_root'))
                    ->get();

                Notification::send($recipients, new CooperativeOrderWorkflowNotification(
                    $suborder,
                    'order.cooperative_response_escalated_to_root',
                    'Intervention root requise',
                    'Aucune décision n’a été prise dans les 24 heures. Vous pouvez maintenant confirmer ou refuser cette sous-commande avec un motif obligatoire.',
                ));

                $suborder->root_escalated_at = now();
                $suborder->forceFill(['updated_by' => null])->save();

                OrderStatusHistory::query()->create([
                    'subject_type' => 'cooperative_order',
                    'subject_id' => (string) $suborder->getKey(),
                    'from_status' => CooperativeOrderStatus::AWAITING_COOPERATIVE->value,
                    'to_status' => CooperativeOrderStatus::AWAITING_COOPERATIVE->value,
                    'actor_id' => null,
                    'actor_role' => 'system',
                    'reason' => 'Absence de décision à H+24 : escalade vers le super_admin_root.',
                    'metadata' => [
                        'action' => 'root_escalation',
                        'recipient_count' => $recipients->count(),
                        'original_deadline_at' => $suborder->created_at->copy()->addHours(24)->toIso8601String(),
                    ],
                    'occurred_at' => now(),
                ]);

                $changed = true;
            }

            return $changed;
        }, 3);
    }
}
