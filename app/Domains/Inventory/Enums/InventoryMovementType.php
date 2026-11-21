<?php

declare(strict_types=1);

namespace App\Domains\Inventory\Enums;

enum InventoryMovementType: string
{
    case OPENING_BALANCE = 'opening_balance';
    case PURCHASE_RECEIPT = 'purchase_receipt';
    case PRODUCTION_ENTRY = 'production_entry';
    case MANUAL_ADJUSTMENT_IN = 'manual_adjustment_in';
    case MANUAL_ADJUSTMENT_OUT = 'manual_adjustment_out';
    case RESERVATION_CREATED = 'reservation_created';
    case RESERVATION_RELEASED = 'reservation_released';
    case SALE = 'sale';
    case CUSTOMER_RETURN = 'customer_return';
    case QUARANTINE_IN = 'quarantine_in';
    case QUARANTINE_RELEASED = 'quarantine_released';
    case QUARANTINE_AUTHORIZED = 'quarantine_authorized';
    case QUARANTINE_AUTHORIZATION_REVOKED = 'quarantine_authorization_revoked';
    case INVENTORY_COUNT_ADJUSTMENT = 'inventory_count_adjustment';
    case DISPOSAL = 'disposal';
    case SAFETY_CANCELLATION_RETURN = 'safety_cancellation_return';
}
