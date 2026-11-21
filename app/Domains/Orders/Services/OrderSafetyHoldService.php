<?php

declare(strict_types=1);

namespace App\Domains\Orders\Services;

use App\Core\Foundation\Services\BaseService;
use App\Domains\Orders\Contracts\OrderSafetyHoldManager;
use App\Domains\Orders\Exceptions\OrderFulfillmentBlocked;
use App\Domains\Orders\Models\CooperativeOrder;
use App\Domains\Orders\Models\OrderSafetyHold;
use Illuminate\Support\Facades\DB;

final class OrderSafetyHoldService extends BaseService implements OrderSafetyHoldManager
{
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
