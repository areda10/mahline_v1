<?php

declare(strict_types=1);

namespace App\Domains\Media\Services;

use App\Domains\Audit\Repositories\AuditEventRepository;
use App\Domains\Audit\Services\AuditService;
use App\Domains\Identity\Users\Models\User;
use App\Domains\Media\Enums\MediaCategory;
use App\Domains\Media\Enums\MediaIncidentStatus;
use App\Domains\Media\Models\MediaIncident;
use App\Domains\Media\Notifications\MediaIncidentNotification;
use App\Shared\DTOs\AuditEntryDTO;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

final class MediaIncidentService
{
    public function __construct(
        private readonly AuditService $audit,
        private readonly AuditEventRepository $auditEvents,
        private readonly MediaService $media,
    ) {}

    public function create(User $actor, string $title, string $description): MediaIncident
    {
        $this->assertRoot($actor);

        $incident = DB::transaction(function () use ($actor, $title, $description): MediaIncident {
            $incident = MediaIncident::query()->create([
                'reference' => 'MI-'.Str::upper((string) Str::ulid()),
                'title' => trim($title),
                'description' => trim($description),
                'status' => MediaIncidentStatus::OPEN,
                'reported_by' => (string) $actor->getKey(),
                'created_by' => (string) $actor->getKey(),
            ]);

            $this->record($actor, $incident, 'media.incident_opened', 'opened');

            return $incident;
        });

        $this->notifyRoots($incident, 'nouvel incident ouvert');

        return $incident;
    }

    public function transition(
        User $actor,
        MediaIncident $incident,
        MediaIncidentStatus $target,
        ?string $resolution = null,
    ): MediaIncident {
        $this->assertRoot($actor);

        $updated = DB::transaction(function () use ($actor, $incident, $target, $resolution): MediaIncident {
            $locked = MediaIncident::query()->whereKey($incident->getKey())->lockForUpdate()->firstOrFail();
            $current = $locked->status;

            $allowed = match ($current) {
                MediaIncidentStatus::OPEN => [MediaIncidentStatus::IN_PROGRESS],
                MediaIncidentStatus::IN_PROGRESS => [MediaIncidentStatus::RESOLVED],
                MediaIncidentStatus::RESOLVED => [MediaIncidentStatus::CLOSED],
                MediaIncidentStatus::CLOSED => [],
            };

            if (! in_array($target, $allowed, true)) {
                throw ValidationException::withMessages([
                    'status' => ['Transition de statut MEDIA non autorisée.'],
                ]);
            }

            if ($target === MediaIncidentStatus::RESOLVED && trim((string) $resolution) === '') {
                throw ValidationException::withMessages([
                    'resolution' => ['Une résolution motivée est obligatoire.'],
                ]);
            }

            $locked->forceFill([
                'status' => $target,
                'resolution' => $target === MediaIncidentStatus::RESOLVED
                    ? trim((string) $resolution)
                    : $locked->resolution,
                'resolved_at' => $target === MediaIncidentStatus::RESOLVED ? now() : $locked->resolved_at,
                'closed_at' => $target === MediaIncidentStatus::CLOSED ? now() : null,
                'updated_by' => (string) $actor->getKey(),
            ])->save();

            $this->record($actor, $locked, 'media.incident_status_changed', 'status_changed');

            return $locked->refresh();
        });

        $this->notifyRoots($updated, 'statut mis à jour');

        return $updated;
    }

    public function reopen(User $actor, MediaIncident $incident, string $reason): MediaIncident
    {
        $this->assertRoot($actor);

        $updated = DB::transaction(function () use ($actor, $incident, $reason): MediaIncident {
            $locked = MediaIncident::query()->whereKey($incident->getKey())->lockForUpdate()->firstOrFail();

            if (! in_array($locked->status, [MediaIncidentStatus::RESOLVED, MediaIncidentStatus::CLOSED], true)) {
                throw ValidationException::withMessages([
                    'status' => ['Seul un incident résolu ou clôturé peut être rouvert.'],
                ]);
            }

            if (trim($reason) === '') {
                throw ValidationException::withMessages([
                    'reason' => ['Le motif de réouverture est obligatoire.'],
                ]);
            }

            $locked->forceFill([
                'status' => MediaIncidentStatus::OPEN,
                'reopen_reason' => trim($reason),
                'reopened_at' => now(),
                'resolved_at' => null,
                'closed_at' => null,
                'updated_by' => (string) $actor->getKey(),
            ])->save();

            $this->record($actor, $locked, 'media.incident_reopened', 'reopened');

            return $locked->refresh();
        });

        $this->notifyRoots($updated, 'incident rouvert');

        return $updated;
    }

    public function recordListView(User $actor): void
    {
        $this->assertRoot($actor);
        $this->recordCollectionEvent($actor, 'media.incidents_listed', 'incidents_listed');
    }

    public function recordView(User $actor, MediaIncident $incident): void
    {
        $this->assertRoot($actor);
        $this->record($actor, $incident, 'media.incident_viewed', 'viewed');
    }

    private function recordCollectionEvent(User $actor, string $eventKey, string $action): void
    {
        if (! $this->auditEvents->verifyIntegrity()) {
            throw new \RuntimeException('La chaîne d’audit est invalide ; consultation incident refusée.');
        }

        $this->audit->record(new AuditEntryDTO(
            eventKey: $eventKey,
            action: $action,
            actorType: User::class,
            actorId: (string) $actor->getKey(),
        ));
    }

    public function recordExport(User $actor): void
    {
        $this->assertRoot($actor);

        if (! $this->auditEvents->verifyIntegrity()) {
            throw new \RuntimeException('La chaîne d’audit est invalide ; export incident refusé.');
        }

        $this->audit->record(new AuditEntryDTO(
            eventKey: 'media.incidents_exported',
            action: 'incidents_exported',
            actorType: User::class,
            actorId: (string) $actor->getKey(),
            metadata: ['format' => 'csv'],
        ));
    }

    public function attach(User $actor, MediaIncident $incident, UploadedFile $file): \App\Domains\Media\Models\Media
    {
        $this->assertRoot($actor);

        return DB::transaction(function () use ($actor, $incident, $file): \App\Domains\Media\Models\Media {
            $locked = MediaIncident::query()->whereKey($incident->getKey())->lockForUpdate()->firstOrFail();

            if ($locked->status === MediaIncidentStatus::CLOSED) {
                throw ValidationException::withMessages([
                    'incident' => ['Aucune pièce jointe ne peut être ajoutée à un incident clôturé.'],
                ]);
            }

            $media = $this->media->upload(
                $actor,
                $locked,
                $file,
                MediaCategory::INCIDENT_ATTACHMENT->value,
            );

            $this->record($actor, $locked, 'media.incident_attachment_added', 'attachment_added');

            return $media;
        });
    }

    private function assertRoot(User $actor): void
    {
        if (! $actor->isAuthorized() || ! $actor->hasRole('super_admin_root')) {
            throw new AccessDeniedHttpException;
        }
    }

    private function record(User $actor, MediaIncident $incident, string $eventKey, string $action): void
    {
        if (! $this->auditEvents->verifyIntegrity()) {
            throw new \RuntimeException('La chaîne d’audit est invalide ; opération incident refusée.');
        }

        $this->audit->record(new AuditEntryDTO(
            eventKey: $eventKey,
            action: $action,
            actorType: User::class,
            actorId: (string) $actor->getKey(),
            subjectType: MediaIncident::class,
            subjectId: (string) $incident->getKey(),
            metadata: [
                'reference' => $incident->reference,
                'status' => $incident->status->value,
            ],
        ));
    }

    private function notifyRoots(MediaIncident $incident, string $event): void
    {
        $roots = User::query()
            ->whereHas('roles', static fn ($query) => $query->where('slug', 'super_admin_root'))
            ->whereNotNull('email')
            ->get();

        try {
            Notification::send($roots, new MediaIncidentNotification($incident, $event));
        } catch (\Throwable $exception) {
            Log::error('MEDIA incident notification delivery failed.', [
                'incident_id' => (string) $incident->getKey(),
                'exception' => $exception::class,
            ]);
        }
    }
}
