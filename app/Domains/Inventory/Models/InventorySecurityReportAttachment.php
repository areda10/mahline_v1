<?php

declare(strict_types=1);

namespace App\Domains\Inventory\Models;

use App\Core\Foundation\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

final class InventorySecurityReportAttachment extends BaseModel
{
    protected $table = 'inventory_security_report_attachments';

    protected $fillable = ['report_id', 'media_id', 'uploaded_by', 'attached_at'];

    protected function casts(): array
    {
        return ['attached_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        self::updating(static function (): never {
            throw new LogicException('Inventory security report attachment records are immutable.');
        });
        self::deleting(static function (): never {
            throw new LogicException('Inventory security report attachment records cannot be deleted.');
        });
    }

    public function report(): BelongsTo
    {
        return $this->belongsTo(InventorySecurityReport::class, 'report_id');
    }
}
