<?php

declare(strict_types=1);

namespace App\Domains\Inventory\Models;

use App\Core\Foundation\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class InventoryReservationAllocation extends BaseModel
{
    protected $table = 'inventory_reservation_allocations';

    protected $fillable = [
        'reservation_item_id',
        'allocation_type',
        'quarantine_authorization_id',
        'quantity',
    ];

    protected function casts(): array
    {
        return ['quantity' => 'decimal:3'];
    }

    public function reservationItem(): BelongsTo
    {
        return $this->belongsTo(InventoryReservationItem::class, 'reservation_item_id');
    }

    public function quarantineAuthorization(): BelongsTo
    {
        return $this->belongsTo(InventoryQuarantineAuthorization::class, 'quarantine_authorization_id');
    }
}
