<?php

declare(strict_types=1);

namespace App\Domains\Inventory\Services\Quarantine;

use App\Core\Foundation\Services\BaseService;
use App\Domains\Inventory\Models\InventoryQuarantine;
use App\Shared\Contracts\ActorContextProvider;
use App\Shared\Contracts\SuperAdminCooperativeAssignmentReader;
use App\Shared\DTOs\ActorContext;
use Illuminate\Auth\Access\AuthorizationException;

final class InventoryQuarantineQueryService extends BaseService
{
    public function __construct(
        private readonly ActorContextProvider $actors,
        private readonly SuperAdminCooperativeAssignmentReader $assignments,
    ) {}

    /** @return list<array<string, mixed>> */
    public function index(string $cooperativeId, string $actorId): array
    {
        $actor = $this->actors->forUserId($actorId);
        $this->authorize($actor, $cooperativeId);

        return InventoryQuarantine::query()
            ->with(['stock', 'authorizations'])
            ->where('cooperative_id', $cooperativeId)
            ->orderByDesc('received_at')
            ->get()
            ->map(static fn (InventoryQuarantine $quarantine): array => [
                'id' => (string) $quarantine->getKey(),
                'stock_id' => (string) $quarantine->stock_id,
                'stockable_type' => (string) $quarantine->stock->stockable_type,
                'stockable_id' => (string) $quarantine->stock->stockable_id,
                'unit_code' => (string) $quarantine->stock->unit_code,
                'quantity_received' => (string) $quarantine->quantity_received,
                'quantity_remaining' => (string) $quarantine->quantity_remaining,
                'quantity_authorized' => (string) $quarantine->quantity_authorized,
                'status' => (string) $quarantine->status,
                'inspection_result' => (string) $quarantine->inspection_result,
                'reason' => (string) $quarantine->reason,
                'inspection_reason' => $quarantine->inspection_reason,
                'received_at' => $quarantine->received_at?->toISOString(),
                'inspected_at' => $quarantine->inspected_at?->toISOString(),
                'authorizations' => $quarantine->authorizations->map(static fn ($authorization): array => [
                    'id' => (string) $authorization->getKey(),
                    'quantity_authorized' => (string) $authorization->quantity_authorized,
                    'quantity_remaining' => (string) $authorization->quantity_remaining,
                    'quantity_reserved' => (string) $authorization->quantity_reserved,
                    'status' => (string) $authorization->status,
                    'reason' => (string) $authorization->reason,
                    'authorized_at' => $authorization->authorized_at?->toISOString(),
                ])->all(),
            ])
            ->all();
    }

    private function authorize(ActorContext $actor, string $cooperativeId): void
    {
        if (! $actor->authorized || ! $actor->hasPermission('inventory.quarantine.manage')) {
            throw new AuthorizationException('The actor cannot view quarantine records.');
        }

        if ($actor->hasRole('super_admin_root')) {
            return;
        }

        if ($actor->hasRole('cooperative_admin') && $actor->cooperativeId === $cooperativeId) {
            return;
        }

        if ($actor->hasRole('super_admin') && $this->assignments->isAssigned($actor->id, $cooperativeId)) {
            return;
        }

        throw new AuthorizationException('The actor is outside the quarantine cooperative scope.');
    }
}
