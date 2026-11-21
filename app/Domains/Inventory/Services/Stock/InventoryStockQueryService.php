<?php

declare(strict_types=1);

namespace App\Domains\Inventory\Services\Stock;

use App\Core\Foundation\Services\BaseService;
use App\Domains\Inventory\Models\InventoryStock;
use App\Shared\Contracts\ActorContextProvider;
use App\Shared\Contracts\SuperAdminCooperativeAssignmentReader;
use App\Shared\DTOs\ActorContext;
use Illuminate\Auth\Access\AuthorizationException;

final class InventoryStockQueryService extends BaseService
{
    public function __construct(
        private readonly ActorContextProvider $actors,
        private readonly SuperAdminCooperativeAssignmentReader $assignments,
    ) {}

    /** @return list<array<string, string>> */
    public function index(string $actorId, ?string $cooperativeId = null): array
    {
        $actor = $this->actors->forUserId($actorId);
        $cooperativeIds = $this->authorizedCooperatives($actor, $cooperativeId);

        $query = InventoryStock::query()->orderBy('cooperative_id')->orderBy('stockable_type')->orderBy('stockable_id');
        if ($cooperativeIds !== null) {
            $query->whereIn('cooperative_id', $cooperativeIds);
        }

        return $query->get()->map(fn (InventoryStock $stock): array => $this->present($stock))->all();
    }

    /** @return array<string, string> */
    public function show(string $stockId, string $actorId): array
    {
        $actor = $this->actors->forUserId($actorId);
        $stock = InventoryStock::query()->findOrFail($stockId);
        $this->authorizeCooperative($actor, (string) $stock->cooperative_id);

        return $this->present($stock);
    }

    /** @return list<string>|null Null means global root scope. */
    private function authorizedCooperatives(ActorContext $actor, ?string $requestedCooperativeId): ?array
    {
        if (! $actor->authorized || ! $actor->hasPermission('inventory.stock.view')) {
            throw new AuthorizationException('The actor cannot view Inventory stock status.');
        }

        if ($actor->hasRole('super_admin_root')) {
            return $requestedCooperativeId === null ? null : [$requestedCooperativeId];
        }

        if ($requestedCooperativeId !== null) {
            $this->authorizeCooperative($actor, $requestedCooperativeId);

            return [$requestedCooperativeId];
        }

        if (($actor->hasRole('cooperative_admin') || $actor->hasRole('user')) && $actor->cooperativeId !== null) {
            return [$actor->cooperativeId];
        }

        throw new AuthorizationException('A cooperative scope is required to view Inventory stock status.');
    }

    private function authorizeCooperative(ActorContext $actor, string $cooperativeId): void
    {
        if (! $actor->authorized || ! $actor->hasPermission('inventory.stock.view')) {
            throw new AuthorizationException('The actor cannot view Inventory stock status.');
        }

        if ($actor->hasRole('super_admin_root')) {
            return;
        }

        if (($actor->hasRole('cooperative_admin') || $actor->hasRole('user'))
            && $actor->cooperativeId === $cooperativeId) {
            return;
        }

        if ($actor->hasRole('super_admin') && $this->assignments->isAssigned($actor->id, $cooperativeId)) {
            return;
        }

        throw new AuthorizationException('The actor is outside the cooperative stock visibility scope.');
    }

    /** @return array<string, string> */
    private function present(InventoryStock $stock): array
    {
        $available = $this->milli($stock->quantity_on_hand)
            - $this->milli($stock->quantity_reserved_normal)
            - $this->milli($stock->quantity_quarantined)
            + $this->milli($stock->quantity_quarantine_authorized)
            - $this->milli($stock->quantity_reserved_quarantine);

        return [
            'id' => (string) $stock->getKey(),
            'cooperative_id' => (string) $stock->cooperative_id,
            'stockable_type' => (string) $stock->stockable_type,
            'stockable_id' => (string) $stock->stockable_id,
            'unit_code' => (string) $stock->unit_code,
            'status' => $available > 0 ? 'En stock' : 'Rupture de stock',
        ];
    }

    private function milli(string|int|float $value): int
    {
        $formatted = (string) $value;
        [$whole, $fraction] = array_pad(explode('.', $formatted, 2), 2, '0');

        return ((int) $whole * 1000) + (int) str_pad($fraction, 3, '0');
    }
}
