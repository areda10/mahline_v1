<?php

declare(strict_types=1);

namespace App\Domains\Orders\Enums;

enum OrderChangeProposalType: string
{
    case QUANTITY_REDUCTION = 'quantity_reduction';
    case PRODUCT_REPLACEMENT = 'product_replacement';
}