<?php

declare(strict_types=1);

namespace App\Domains\Inventory\Services\Counts;

use App\Core\Foundation\Services\BaseService;
use App\Domains\Inventory\Models\InventoryCountSession;
use App\Shared\Contracts\ActorContextProvider;
use App\Shared\Contracts\SuperAdminCooperativeAssignmentReader;
use App\Shared\DTOs\ActorContext;
use Illuminate\Auth\Access\AuthorizationException;

final class InventoryCountQueryService extends BaseService
{
    public function __construct(
        private readonly ActorContextProvider $actors,
        private readonly SuperAdminCooperativeAssignmentReader $assignments,
    ) {}

    /** @return list<array<string, mixed>> */
    public function sessionsForCooperative(string $cooperativeId, string $actorId): array
    {
        $actor = $this->actors->forUserId($actorId);
        $this->authorize($actor, $cooperativeId);

        return InventoryCountSession::query()
            ->with(['items', 'participants'])
            ->where('cooperative_id', $cooperativeId)
            ->orderByDesc('started_at')
            ->get()
            ->map(static fn (InventoryCountSession $session): array => [
                'id' => (string) $session->getKey(),
                'cooperative_id' => (string) $session->cooperative_id,
                'status' => (string) $session->status,
                'started_at' => $session->started_at?->toISOString(),
                'completed_at' => $session->completed_at?->toISOString(),
                'items_count' => $session->items->count(),
                'counted_items_count' => $session->items->whereNotNull('counted_quantity')->count(),
                'participants_count' => $session->participants->count(),
            ])
            ->all();
    }

    /** @return array<string, mixed> */
    public function show(string $sessionId, string $actorId): array
    {
        $session = InventoryCountSession::query()
            ->with(['items.stock', 'participants'])
            ->findOrFail($sessionId);
        $actor = $this->actors->forUserId($actorId);
        $this->authorize($actor, (string) $session->cooperative_id);

        $managerView = $actor->hasRole('super_admin_root')
            || $actor->hasRole('super_admin')
            || $actor->hasRole('cooperative_admin');

        return [
            'id' => (string) $session->getKey(),
            'cooperative_id' => (string) $session->cooperative_id,
            'status' => (string) $session->status,
            'started_at' => $session->started_at?->toISOString(),
            'completed_at' => $session->completed_at?->toISOString(),
            'items' => $session->items->map(function ($item) use ($managerView): array {
                $data = [
                    'id' => (string) $item->getKey(),
                    'stock_id' => (string) $item->stock_id,
                    'stockable_type' => (string) $item->stock->stockable_type,
                    'stockable_id' => (string) $item->stock->stockable_id,
                    'unit_code' => (string) $item->stock->unit_code,
                    'status' => (string) $item->status,
                    'counted_quantity' => $item->counted_quantity === null ? null : (string) $item->counted_quantity,
                ];

                if ($managerView) {
                    $data['expected_quantity'] = (string) $item->expected_quantity;
                    $data['difference_quantity'] = $item->difference_quantity === null ? null : (string) $item->difference_quantity;
                    $data['difference_reason'] = $item->difference_reason;
                }

                return $data;
            })->all(),
            'participants' => $managerView
                ? $session->participants->map(fn ($participant): array => [
                    'user_id' => (string) $participant->user_id,
                    'role' => (string) $participant->participant_role,
                    'joined_at' => $participant->joined_at?->toISOString(),
                ])->all()
                : [],
        ];
    }

    private function authorize(ActorContext $actor, string $cooperativeId): void
    {
        if (! $actor->authorized || ! $actor->hasPermission('inventory.count.perform') && ! $actor->hasPermission('inventory.count.view')) {
            throw new AuthorizationException('The actor cannot view this physical count session.');
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

        throw new AuthorizationException('The actor is outside the physical count session scope.');
    }
}
