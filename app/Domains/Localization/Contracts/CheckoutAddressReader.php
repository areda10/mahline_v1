<?php

declare(strict_types=1);

namespace App\Domains\Localization\Contracts;

use App\Domains\Localization\DTOs\CheckoutAddressSnapshot;

interface CheckoutAddressReader
{
    public function snapshotForCustomer(string $customerId, string $addressId): CheckoutAddressSnapshot;
}
