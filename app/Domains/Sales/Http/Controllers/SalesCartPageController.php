<?php

declare(strict_types=1);

namespace App\Domains\Sales\Http\Controllers;

use Illuminate\View\View;

final class SalesCartPageController
{
    public function show(): View
    {
        return view('storefront.cart');
    }
}
