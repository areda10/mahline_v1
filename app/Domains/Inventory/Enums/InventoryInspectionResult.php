<?php

declare(strict_types=1);

namespace App\Domains\Inventory\Enums;

enum InventoryInspectionResult: string
{
    case COMPLIANT = 'compliant';
    case NON_COMPLIANT = 'non_compliant';
}
