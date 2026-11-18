<?php

declare(strict_types=1);

namespace App\Domains\Media\Models;

use App\Core\Foundation\Models\BaseModel;
use App\Domains\Identity\Users\Models\User;
use App\Domains\Media\Enums\MediaIncidentStatus;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

final class MediaIncident extends BaseModel
{
    protected $table = 'media_incidents';

    protected function casts(): array
    {
        return [
            'status' => MediaIncidentStatus::class,
            'resolved_at' => 'datetime',
            'closed_at' => 'datetime',
            'reopened_at' => 'datetime',
        ];
    }

    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reported_by');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function media(): MorphMany
    {
        return $this->morphMany(Media::class, 'mediable');
    }

    public function attachments(): MorphMany
    {
        return $this->media()->where('category', 'incident_attachment')->orderBy('created_at');
    }
}
