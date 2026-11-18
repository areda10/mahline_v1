<?php

declare(strict_types=1);

namespace App\Domains\Orders\Services;

use App\Domains\Identity\Users\Models\User;
use App\Domains\Orders\Enums\CooperativeOrderStatus;
use App\Domains\Orders\Enums\OrderStatus;
use App\Domains\Orders\Models\CooperativeOrder;
use App\Domains\Orders\Models\Order;
use App\Domains\Orders\Models\OrderStatusHistory;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class CooperativeOrderResponseService
{
    public function confirmByCooperative(CooperativeOrder $cooperativeOrder, User $actor): CooperativeOrder
    {
        return $this->respond($cooperativeOrder, $actor, true, null, false);
    }

    public function refuseByCooperative(
        CooperativeOrder $cooperativeOrder,
        User $actor,
        string $reason,
    ): CooperativeOrder {
        return $this->respond($cooperativeOrder, $actor, false, $reason, false);
    }

    /**
     * Administrative substitution is restricted to root or the super-admin
     * assigned to this cooperative, and is always reasoned and attributed.
     */
    public function decideOnBehalf(
        CooperativeOrder $cooperativeOrder,
        User $actor,
        bool $accept,
        string $reason,
    ): CooperativeOrder {
        if (! $actor->hasRole('super_admin_root') && ! $this->isAssignedSuperAdmin($actor, $cooperativeOrder)) {
            abort(403);
        }

        if (trim($reason) === '') {
            throw ValidationException::withMessages([
                'reason' => ['Le motif de l’intervention administrative est obligatoire.'],
            ]);
        }

        return $this->respond($cooperativeOrder, $actor, $accept, $reason, true);
    }

    public function extendResponseDeadline(
        CooperativeOrder $cooperativeOrder,
        User $actor,
        \DateTimeInterface $newDeadline,
        string $reason,
    ): CooperativeOrder {
        if (! $actor->hasRole('super_admin_root') && ! $this->isAssignedSuperAdmin($actor, $cooperativeOrder)) {
            abort(403);
        }

        if (trim($reason) === '') {
            throw ValidationException::withMessages([
                'reason' => ['Le motif de la prolongation est obligatoire.'],
            ]);
        }

        $deadline = \Illuminate\Support\Carbon::instance($newDeadline);
        if ($deadline->lessThanOrEqualTo(now()) || $deadline->greaterThan(now()->addHours(24))) {
            throw ValidationException::withMessages([
                'response_deadline_at' => ['La nouvelle échéance doit être future et ne peut pas dépasser 24 heures.'],
            ]);
        }

        return DB::transaction(function () use ($cooperativeOrder, $actor, $deadline, $reason): CooperativeOrder {
            $locked = CooperativeOrder::query()
                ->whereKey($cooperativeOrder->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if ($locked->status !== CooperativeOrderStatus::AWAITING_COOPERATIVE) {
                throw ValidationException::withMessages([
                    'cooperative_order' => ['Seule une sous-commande en attente peut être prolongée.'],
                ]);
            }

            if ($locked->response_deadline_at !== null && $deadline->lessThanOrEqualTo($locked->response_deadline_at)) {
                throw ValidationException::withMessages([
                    'response_deadline_at' => ['La nouvelle échéance doit être postérieure à l’échéance actuelle.'],
                ]);
            }

            $locked->response_deadline_at = $deadline;
            $locked->response_overdue_at = null;
            $locked->forceFill(['updated_by' => (string) $actor->getKey()])->save();

            OrderStatusHistory::query()->create([
                'subject_type' => 'cooperative_order',
                'subject_id' => (string) $locked->getKey(),
                'from_status' => $locked->status->value,
                'to_status' => $locked->status->value,
                'actor_id' => (string) $actor->getKey(),
                'actor_role' => $actor->hasRole('super_admin_root') ? 'super_admin_root' : 'super_admin',
                'reason' => trim($reason),
                'metadata' => [
                    'action' => 'response_deadline_extended',
                    'new_deadline' => $deadline->toIso8601String(),
                ],
                'occurred_at' => now(),
            ]);

            return $locked->refresh();
        }, 3);
    }

    private function respond(
        CooperativeOrder $cooperativeOrder,
        User $actor,
        bool $accept,
        ?string $reason,
        bool $administrative,
    ): CooperativeOrder {
        return DB::transaction(function () use (
            $cooperativeOrder,
            $actor,
            $accept,
            $reason,
            $administrative,
        ): CooperativeOrder {
            $locked = CooperativeOrder::query()
                ->whereKey($cooperativeOrder->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if ($administrative && ($locked->response_deadline_at === null || $locked->response_deadline_at->isFuture())) {
                throw ValidationException::withMessages([
                    'cooperative_order' => ['Une intervention administrative est possible uniquement après l’échéance de réponse.'],
                ]);
            }

            if ($locked->status !== CooperativeOrderStatus::AWAITING_COOPERATIVE) {
                throw ValidationException::withMessages([
                    'cooperative_order' => ['Cette sous-commande a déjà reçu une décision.'],
                ]);
            }

            if (! $administrative && ! $this->canRespondForCooperative($actor, $locked)) {
                abort(403);
            }

            $fromStatus = $locked->status->value;
            $newStatus = $accept
                ? CooperativeOrderStatus::CONFIRMED
                : CooperativeOrderStatus::REFUSED;

            $locked->status = $newStatus;
            $locked->responded_at = now();

            if ($accept) {
                $locked->confirmed_at = now();
            } else {
                $locked->refused_at = now();
                $locked->refusal_reason = trim((string) $reason);
            }

            if ($administrative) {
                $locked->substituted_by_admin_id = (string) $actor->getKey();
                $locked->substitution_reason = trim((string) $reason);
            }

            $locked->forceFill(['updated_by' => (string) $actor->getKey()])->save();

            OrderStatusHistory::query()->create([
                'subject_type' => 'cooperative_order',
                'subject_id' => (string) $locked->getKey(),
                'from_status' => $fromStatus,
                'to_status' => $newStatus->value,
                'actor_id' => (string) $actor->getKey(),
                'actor_role' => $actor->hasRole('super_admin_root')
                    ? 'super_admin_root'
                    : ($actor->hasRole('super_admin') ? 'super_admin' : 'cooperative_admin'),
                'reason' => $reason,
                'metadata' => $administrative ? ['administrative_substitution' => true] : null,
                'occurred_at' => now(),
            ]);

            $this->refreshGlobalOrderStatus($locked->order_id, $actor);

            return $locked->refresh();
        }, 3);
    }

    private function refreshGlobalOrderStatus(string $orderId, User $actor): void
    {
        $order = Order::query()->whereKey($orderId)->lockForUpdate()->firstOrFail();
        $statuses = $order->cooperativeOrders()->pluck('status')->map(
            static fn ($status): string => $status instanceof CooperativeOrderStatus
                ? $status->value
                : (string) $status,
        );

        $confirmed = $statuses->filter(
            static fn (string $status): bool => $status === CooperativeOrderStatus::CONFIRMED->value,
        )->count();
        $refused = $statuses->filter(
            static fn (string $status): bool => $status === CooperativeOrderStatus::REFUSED->value,
        )->count();
        $total = $statuses->count();

        $next = match (true) {
            $total > 0 && $confirmed === $total => OrderStatus::CONFIRMED,
            $total > 0 && $refused === $total => OrderStatus::REFUSED,
            $confirmed > 0 && $refused > 0 => OrderStatus::PARTIALLY_REFUSED,
            $confirmed > 0 => OrderStatus::PARTIALLY_CONFIRMED,
            $refused > 0 => OrderStatus::PARTIALLY_REFUSED,
            default => $order->status,
        };

        if ($next === $order->status) {
            return;
        }

        $fromStatus = $order->status->value;
        $order->status = $next;
        if ($next === OrderStatus::CONFIRMED) {
            $order->confirmed_at = now();
        }
        $order->forceFill(['updated_by' => (string) $actor->getKey()])->save();

        OrderStatusHistory::query()->create([
            'subject_type' => 'order',
            'subject_id' => (string) $order->getKey(),
            'from_status' => $fromStatus,
            'to_status' => $next->value,
            'actor_id' => (string) $actor->getKey(),
            'actor_role' => $actor->hasRole('super_admin_root')
                ? 'super_admin_root'
                : ($actor->hasRole('super_admin') ? 'super_admin' : 'cooperative_admin'),
            'reason' => 'Agrégation des décisions des sous-commandes coopératives.',
            'metadata' => null,
            'occurred_at' => now(),
        ]);
    }

    private function canRespondForCooperative(User $actor, CooperativeOrder $cooperativeOrder): bool
    {
        if ($actor->hasRole('cooperative_admin')) {
            return (string) $actor->cooperative_id === (string) $cooperativeOrder->cooperative_id;
        }

        if ($actor->hasRole('super_admin_root')) {
            return true;
        }

        return $this->isAssignedSuperAdmin($actor, $cooperativeOrder);
    }

    private function isAssignedSuperAdmin(User $actor, CooperativeOrder $cooperativeOrder): bool
    {
        return $actor->hasRole('super_admin')
            && $actor->cooperatives()
                ->whereKey($cooperativeOrder->cooperative_id)
                ->exists();
    }
}
