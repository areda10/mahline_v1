<?php

declare(strict_types=1);

namespace App\Domains\Orders\Enums;

enum OrderChangeProposalStatus: string
{
    case PENDING_CUSTOMER = 'pending_customer';
    case ACCEPTED = 'accepted';
    case REFUSED = 'refused';
    case EXPIRED = 'expired';
    case CANCELLED = 'cancelled';
}