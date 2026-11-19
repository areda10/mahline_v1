<?php

declare(strict_types=1);

namespace App\Domains\Media\Observers;

use App\Domains\Audit\Contracts\AuditWriter;
use App\Domains\Cooperatives\Models\Cooperative;
use App\Domains\Identity\Users\Models\User;
use App\Domains\Products\Models\Product;
use App\Shared\DTOs\AuditEntryDTO;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class MediaOwnerObserver
{
    public function restoring(Model $owner): void
    {
        if (! $owner instanceof Product && ! $owner instanceof Cooperative && ! $owner instanceof User) {
            return;
        }

        $actor = Auth::user();
        $actorId = $actor instanceof User ? (string) $actor->getKey() : null;

        if (! app(AuditWriter::class)->verifyIntegrity()) {
            throw new RuntimeException('La chaîne d’audit est invalide ; restauration du propriétaire refusée.');
        }

        DB::transaction(function () use ($owner, $actor, $actorId): void {
            $owner->media()->lockForUpdate()->get()->each(function ($media) use ($owner, $actor, $actorId): void {
                if (app(\App\Domains\Media\Services\MediaService::class)->isMediaIntegrityValid($media)) {
                    return;
                }

                $media->forceFill(['deleted_by' => $actorId])->save();
                $media->delete();

                app(AuditWriter::class)->record(new AuditEntryDTO(
                    eventKey: 'media.owner_restore_skipped_invalid',
                    action: 'owner_restore_skipped_invalid',
                    actorType: $actor instanceof User ? User::class : null,
                    actorId: $actorId,
                    subjectType: $media::class,
                    subjectId: (string) $media->getKey(),
                    metadata: [
                        'category' => $media->category,
                        'owner_type' => $owner::class,
                        'reason' => 'missing_or_corrupt_file',
                    ],
                ));
            });

            app(AuditWriter::class)->record(new AuditEntryDTO(
                eventKey: 'media.owner_restore_prepared',
                action: 'owner_restore_prepared',
                actorType: $actor instanceof User ? User::class : null,
                actorId: $actorId,
                subjectType: $owner::class,
                subjectId: (string) $owner->getKey(),
                metadata: ['owner_type' => $owner::class],
            ));
        });
    }

    public function deleting(Model $owner): void
    {
        if (! $owner instanceof Product && ! $owner instanceof Cooperative && ! $owner instanceof User) {
            return;
        }

        $actor = Auth::user();
        $actorId = $actor instanceof User ? (string) $actor->getKey() : null;

        if (! method_exists($owner, 'isForceDeleting') || ! $owner->isForceDeleting()) {
            if (! app(AuditWriter::class)->verifyIntegrity()) {
                throw new RuntimeException('La chaîne d’audit est invalide ; suppression logique du propriétaire refusée.');
            }

            app(AuditWriter::class)->record(new AuditEntryDTO(
                eventKey: 'media.owner_soft_deleted',
                action: 'owner_soft_deleted',
                actorType: $actor instanceof User ? User::class : null,
                actorId: $actorId,
                subjectType: $owner::class,
                subjectId: (string) $owner->getKey(),
                metadata: [
                    'owner_type' => $owner::class,
                    'media_count' => $owner->media()->count(),
                ],
            ));

            return;
        }

        if (! app(AuditWriter::class)->verifyIntegrity()) {
            throw new RuntimeException('La chaîne d’audit est invalide ; suppression définitive du propriétaire refusée.');
        }

        DB::transaction(function () use ($owner, $actorId, $actor): void {
            $owner->media()->whereNull('deleted_at')->lockForUpdate()->get()->each(
            function ($media) use ($actorId, $owner, $actor): void {
                $media->forceFill(['deleted_by' => $actorId])->save();
                $media->delete();

                $cooperativeId = match (true) {
                    $owner instanceof Product => (string) $owner->cooperative_id,
                    $owner instanceof Cooperative => (string) $owner->getKey(),
                    $owner instanceof User => $owner->cooperative_id === null ? null : (string) $owner->cooperative_id,
                    default => null,
                };

                app(AuditWriter::class)->record(new AuditEntryDTO(
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
        });
    }
}
