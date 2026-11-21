<?php

declare(strict_types=1);

namespace App\Domains\Inventory\Services\Stock;

use App\Core\Foundation\Services\BaseService;
use App\Domains\Inventory\Models\InventoryStock;
use App\Shared\Contracts\ActorContextProvider;
use App\Shared\Contracts\SuperAdminCooperativeAssignmentReader;
use App\Shared\DTOs\ActorContext;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Collection;

final class InventoryStockReadService extends BaseService
{
    public function __construct(
        private readonly ActorContextProvider $actors,
        private readonly SuperAdminCooperativeAssignmentReader $assignments,
    ) {}

    /** @return Collection<int, array{stock_id: string, stockable_type: string, stockable_id: string, unit_code: string, status: string}> */
    public function availability(string $cooperativeId, string $actorId): Collection
    {
        $actor = $this->actors->forUserId($actorId);
        $this->authorize($actor, $cooperativeId);

        return InventoryStock::query()
            ->where('cooperative_id', $cooperativeId)
            ->orderBy('stockable_type')
            ->orderBy('stockable_id')
            ->get()
            ->map(function (InventoryStock $stock): array {
                $available = $stock->active_count_session_id !== null ? 0 : (
                    $this->milli($stock->quantity_on_hand)
                    - $this->milli($stock->quantity_reserved_normal)
                    - $this->milli($stock->quantity_quarantined)
                    + $this->milli($stock->quantity_quarantine_authorized)
                    - $this->milli($stock->quantity_reserved_quarantine)
                );

                return [
                    'stock_id' => (string) $stock->getKey(),
                    'stockable_type' => (string) $stock->stockable_type,
                    'stockable_id' => (string) $stock->stockable_id,
                    'unit_code' => (string) $stock->unit_code,
                    'status' => $available > 0 ? 'En stock' : 'Rupture de stock',
                ];
            });
    }

    private function authorize(ActorContext $actor, string $cooperativeId): void
    {
        if (! $actor->authorized || ! $actor->hasPermission('inventory.stock.view')) {
            throw new AuthorizationException('The actor cannot view Inventory availability.');
        }
        if ($actor->hasRole('super_admin_root')) {
            return;
        }
        if ($actor->hasRole('super_admin') && $this->assignments->isAssigned($actor->id, $cooperativeId)) {
            return;
        }
        if ($actor->hasAnyRole(['cooperative_admin', 'user']) && $actor->cooperativeId === $cooperativeId) {
            return;
        }
        throw new AuthorizationException('The actor is outside the cooperative scope.');
    }

    private function milli(string|int|float $value): int
    {
        $formatted = (string) $value;
        [$whole, $fraction] = array_pad(explode('.', $formatted, 2), 2, '0');

        return ((int) $whole * 1000) + (int) str_pad($fraction, 3, '0', STR_PAD_LEFT);
    }
}
