<?php

declare(strict_types=1);

namespace App\Domains\Orders\Models;

use App\Core\Foundation\Models\BaseModel;
use App\Domains\Identity\Users\Models\User;
use App\Domains\Orders\Enums\OrderChangeProposalStatus;
use App\Domains\Orders\Enums\OrderChangeProposalType;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class OrderChangeProposal extends BaseModel
{
    protected $table = 'order_change_proposals';

    protected $fillable = [
        'order_item_id', 'proposed_by_user_id', 'proposal_type', 'proposed_quantity',
        'replacement_product_id', 'replacement_variant_id', 'replacement_snapshot',
        'reason', 'status', 'customer_response_deadline_at', 'customer_responded_at', 'expired_at',
    ];

    protected function casts(): array
    {
        return [
            'proposal_type' => OrderChangeProposalType::class,
            'proposed_quantity' => 'decimal:3',
            'replacement_snapshot' => 'array',
            'status' => OrderChangeProposalStatus::class,
            'customer_response_deadline_at' => 'datetime',
            'customer_responded_at' => 'datetime',
            'expired_at' => 'datetime',
        ];
    }

    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class, 'order_item_id');
    }

    public function proposedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'proposed_by_user_id');
    }
}