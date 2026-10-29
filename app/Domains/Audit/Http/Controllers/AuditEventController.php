<?php

declare(strict_types=1);

namespace App\Domains\Audit\Http\Controllers;

use App\Domains\Audit\Http\Requests\IndexAuditEventsRequest;
use App\Domains\Audit\Http\Resources\AuditEventResource;
use App\Domains\Audit\Models\AuditEvent;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class AuditEventController
{
    public function index(IndexAuditEventsRequest $request): AnonymousResourceCollection
    {
        $this->authorizeViewAny();

        $user = $request->user();
        $query = AuditEvent::query()->orderByDesc('occurred_at');

        if (! $user->hasRole('super_admin_root')) {
            if ($user->hasRole('super_admin')) {
                $cooperativeIds = $user->cooperatives()->pluck('cooperatives.id');
                $query->whereIn('cooperative_id', $cooperativeIds);
            } elseif ($user->cooperative_id !== null) {
                $query->where('cooperative_id', $user->cooperative_id);
            } else {
                $query->whereRaw('1 = 0');
            }
        }

        $filters = $request->validated();

        foreach (['event_key', 'subject_type', 'subject_id'] as $field) {
            if (isset($filters[$field]) && $filters[$field] !== '') {
                $query->where($field, $filters[$field]);
            }
        }

        if (isset($filters['from'])) {
            $query->where('occurred_at', '>=', $filters['from']);
        }

        if (isset($filters['to'])) {
            $query->where('occurred_at', '<=', $filters['to']);
        }

        return AuditEventResource::collection(
            $query->paginate((int) ($filters['per_page'] ?? 25)),
        );
    }

    private function authorizeViewAny(): void
    {
        abort_unless(
            auth()->user()?->can('viewAny', AuditEvent::class),
            403,
        );
    }
}
