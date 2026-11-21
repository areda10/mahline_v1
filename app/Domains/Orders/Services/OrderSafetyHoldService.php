<?php

declare(strict_types=1);

namespace App\Domains\Orders\Services;

use App\Core\Foundation\Services\BaseService;
use App\Domains\Audit\Contracts\AuditWriter;
use App\Shared\DTOs\AuditEntryDTO;
use App\Domains\Orders\Contracts\OrderSafetyHoldManager;
use App\Domains\Orders\Exceptions\OrderFulfillmentBlocked;
use App\Domains\Orders\Models\CooperativeOrder;
use App\Domains\Orders\Models\OrderSafetyHold;
use Illuminate\Support\Facades\DB;

final class OrderSafetyHoldService extends BaseService implements OrderSafetyHoldManager
{
    public function __construct(private readonly AuditWriter $audit) {}
    public function raiseHold(
        string $cooperativeOrderId,
        string $sourceType,
        string $sourceId,
        string $reason,
        ?string $actorId = null,
        ?string $actorType = 'system',
    ): string {
        return DB::transaction(function () use (
            $cooperativeOrderId,
            $sourceType,
            $sourceId,
            $reason,
            $actorId,
            $actorType,
        ): string {
            $cooperativeOrder = CooperativeOrder::query()
                ->whereKey($cooperativeOrderId)
                ->lockForUpdate()
                ->firstOrFail();

            $hold = OrderSafetyHold::query()->firstOrCreate(
                [
                    'cooperative_order_id' => $cooperativeOrderId,
                    'source_type' => $sourceType,
                    'source_id' => $sourceId,
                ],
                [
                    'cooperative_id' => (string) $cooperativeOrder->cooperative_id,
                    'reason' => trim($reason),
                    'status' => 'active',
                    'raised_by_id' => $actorId,
                    'raised_by_type' => $actorType,
                    'raised_at' => now(),
                ],
            );

            $wasRecentlyCreated = $hold->wasRecentlyCreated;
            $previousStatus = $hold->status;
            if ($hold->status !== 'active') {
                $hold->status = 'active';
                $hold->reason = trim($reason);
                $hold->raised_by_id = $actorId;
                $hold->raised_by_type = $actorType;
                $hold->raised_at = now();
                $hold->resolved_by_id = null;
                $hold->resolved_by_type = null;
                $hold->resolution = null;
                $hold->resolution_reason = null;
                $hold->resolved_at = null;
                $hold->save();
            }

            $this->audit->record(new AuditEntryDTO(
                eventKey: $wasRecentlyCreated ? 'orders.safety_hold.raised' : ($previousStatus === 'active' ? 'orders.safety_hold.duplicate' : 'orders.safety_hold.reopened'),
                action: $wasRecentlyCreated ? 'create' : 'update',
                actorType: $actorType ?? 'system',
                actorId: $actorId,
                cooperativeId: (string) $cooperativeOrder->cooperative_id,
                subjectType: 'order_safety_hold',
                subjectId: (string) $hold->getKey(),
                oldValues: $wasRecentlyCreated ? [] : ['status' => $previousStatus],
                newValues: ['status' => $hold->status, 'source_type' => $sourceType, 'source_id' => $sourceId, 'reason' => trim($reason)],
            ));

            return (string) $hold->getKey();
        }, 3);
    }

    public function assertCanShip(string $cooperativeOrderId): void
    {
        $hasActiveHold = OrderSafetyHold::query()
            ->where('cooperative_order_id', $cooperativeOrderId)
            ->where('status', 'active')
            ->exists();

        if ($hasActiveHold) {
            throw new OrderFulfillmentBlocked('Expédition interdite : un blocage de sécurité est actif sur cette sous-commande.');
        }
    }
}
