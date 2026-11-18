<?php

declare(strict_types=1);

namespace App\Domains\Media\Observers;

use App\Domains\Audit\Repositories\AuditEventRepository;
use App\Domains\Audit\Services\AuditService;
use App\Domains\Cooperatives\Models\Cooperative;
use App\Domains\Identity\Users\Models\User;
use App\Domains\Products\Models\Product;
use App\Shared\DTOs\AuditEntryDTO;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use RuntimeException;

final class MediaOwnerObserver
{
    public function __construct(
        private readonly AuditService $audit,
        private readonly AuditEventRepository $events,
    ) {}

    public function deleting(Model $owner): void
    {
        if (! method_exists($owner, 'isForceDeleting') || ! $owner->isForceDeleting()) {
            return;
        }

        if (! $owner instanceof Product && ! $owner instanceof Cooperative && ! $owner instanceof User) {
            return;
        }

        $actor = Auth::user();
        $actorId = $actor instanceof User ? (string) $actor->getKey() : null;

        if (! $this->events->verifyIntegrity()) {
            throw new RuntimeException('La chaîne d’audit est invalide ; suppression définitive du propriétaire refusée.');
        }

        $owner->media()->whereNull('deleted_at')->lockForUpdate()->get()->each(
            function ($media) use ($actorId, $owner): void {
                $media->forceFill(['deleted_by' => $actorId])->save();
                $media->delete();

                $cooperativeId = match (true) {
                    $owner instanceof Product => (string) $owner->cooperative_id,
                    $owner instanceof Cooperative => (string) $owner->getKey(),
                    $owner instanceof User => $owner->cooperative_id === null ? null : (string) $owner->cooperative_id,
                    default => null,
                };

                $this->audit->record(new AuditEntryDTO(
                    eventKey: 'media.owner_force_deleted',
                    action: 'owner_force_deleted',
                    actorType: $actor instanceof User ? User::class : null,
                    actorId: $actorId,
                    cooperativeId: $cooperativeId,
                    subjectType: $media::class,
                    subjectId: (string) $media->getKey(),
                    metadata: [
                        'category' => $media->category,
                        'owner_type' => $owner::class,
                    ],
                ));
            },
        );
    }
}
