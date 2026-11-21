<?php

declare(strict_types=1);

namespace App\Domains\Inventory\Models;

use App\Core\Foundation\Models\BaseModel;
use App\Shared\Contracts\PrivateMediaAttachmentOwner;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class InventorySecurityReport extends BaseModel implements PrivateMediaAttachmentOwner
{
    protected $table = 'inventory_security_reports';

    protected $fillable = [
        'cooperative_id', 'cooperative_order_id', 'reporter_id', 'reporter_role',
        'category', 'description', 'urgency', 'status', 'response_due_at',
        'escalated_at', 'closed_by', 'closed_at', 'closure_reason',
        'reopened_by', 'reopened_at', 'reopen_reason',
    ];

    protected function casts(): array
    {
        return [
            'response_due_at' => 'datetime',
            'escalated_at' => 'datetime',
            'closed_at' => 'datetime',
            'reopened_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::deleting(static function (): never {
            throw new LogicException('Inventory historical records cannot be deleted.');
        });
    }

    public function media(): MorphMany
    {
        return $this->morphMany('App\\Domains\\Media\\Models\\Media', 'mediable');
    }

    public function privateMediaCooperativeId(): string
    {
        return (string) $this->cooperative_id;
    }

    public function privateMediaReporterId(): string
    {
        return (string) $this->reporter_id;
    }

    public function privateMediaUploadOpen(): bool
    {
        return $this->status === 'draft';
    }

    public function events(): HasMany
    {
        return $this->hasMany(InventorySecurityReportEvent::class, 'report_id')->orderBy('occurred_at');
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(InventorySecurityReportAttachment::class, 'report_id');
    }
}
