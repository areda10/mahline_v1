<?php

declare(strict_types=1);

namespace App\Domains\Media\Observers;

use App\Domains\Audit\Contracts\AuditWriter;
use App\Domains\Cooperatives\Models\Cooperative;
use App\Domains\Identity\Users\Models\User;
use App\Domains\Media\Services\MediaService;
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
                if (app(MediaService::class)->isMediaIntegrityValid($media)) {
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

    public function restored(Model $owner): void
    {
        if (! $owner instanceof Cooperative) {
            return;
        }

        $actor = Auth::user();
        if (! $actor instanceof User || ! $actor->hasRole('super_admin_root') || ! $actor->isAuthorized()) {
            throw new RuntimeException('La restauration des médias privés d’une coopérative exige un super_admin_root autorisé.');
        }

        if (! app(AuditWriter::class)->verifyIntegrity()) {
            throw new RuntimeException('La chaîne d’audit est invalide ; restauration des médias privés refusée.');
        }

        $retainedPrivateDocuments = $owner->media()
            ->onlyTrashed()
            ->where('category', 'document')
            ->where('visibility', 'private')
            ->orderBy('id')
            ->lockForUpdate()
            ->get();

        foreach ($retainedPrivateDocuments as $media) {
            try {
                app(MediaService::class)->restore($actor, $media);
            } catch (\Illuminate\Validation\ValidationException) {
                // Missing or altered retained files remain soft-deleted and auditable.
                continue;
            }
        }

        app(AuditWriter::class)->record(new AuditEntryDTO(
            eventKey: 'media.cooperative_private_media_restore_processed',
            action: 'cooperative_private_media_restore_processed',
            actorType: User::class,
            actorId: (string) $actor->getKey(),
            cooperativeId: (string) $owner->getKey(),
            subjectType: Cooperative::class,
            subjectId: (string) $owner->getKey(),
            metadata: [
                'retained_documents_found' => $retainedPrivateDocuments->count(),
                'retention_days' => (int) config('media.retention_days', 90),
            ],
        ));
    }

    public function deleting(Model $owner): void
    {
        if (! $owner instanceof Product && ! $owner instanceof Cooperative && ! $owner instanceof User) {
            return;
        }

        // Identity owns the protected-root deletion rule. Do not emit Media
        // audit events before UserObserver rejects deletion of that account.
        if ($owner instanceof User && $owner->roles()->where('slug', 'super_admin_root')->exists()) {
            return;
        }

        $actor = Auth::user();
        $actorId = $actor instanceof User ? (string) $actor->getKey() : null;

        if (! method_exists($owner, 'isForceDeleting') || ! $owner->isForceDeleting()) {
            if (! app(AuditWriter::class)->verifyIntegrity()) {
                throw new RuntimeException('La chaîne d’audit est invalide ; suppression logique du propriétaire refusée.');
            }

            if ($owner instanceof Cooperative) {
                $privateDocuments = $owner->media()
                    ->where('category', 'document')
                    ->where('visibility', 'private')
                    ->whereNull('deleted_at')
                    ->lockForUpdate()
                    ->get();

                foreach ($privateDocuments as $media) {
                    $media->forceFill(['deleted_by' => $actorId])->save();
                    $media->delete();

                    app(AuditWriter::class)->record(new AuditEntryDTO(
                        eventKey: 'media.cooperative_private_media_soft_deleted',
                        action: 'cooperative_private_media_soft_deleted',
                        actorType: $actor instanceof User ? User::class : null,
                        actorId: $actorId,
                        cooperativeId: (string) $owner->getKey(),
                        subjectType: $media::class,
                        subjectId: (string) $media->getKey(),
                        metadata: [
                            'category' => $media->category,
                            'retention_days' => (int) config('media.retention_days', 90),
                            'owner_deleted_at' => now()->toISOString(),
                        ],
                    ));
                }
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
