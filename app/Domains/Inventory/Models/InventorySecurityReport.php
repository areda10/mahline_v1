<?php

declare(strict_types=1);

namespace App\Domains\Inventory\Models;

use App\Core\Foundation\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class InventorySecurityReport extends BaseModel
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

    public function events(): HasMany
    {
        return $this->hasMany(InventorySecurityReportEvent::class, 'report_id')->orderBy('occurred_at');
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(InventorySecurityReportAttachment::class, 'report_id');
    }
}
