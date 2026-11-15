<?php

declare(strict_types=1);

namespace App\Domains\Identity\Customer\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\View\View;

final class CustomerDashboardController
{
    public function __invoke(Request $request): View
    {
        return view('customer.dashboard', [
            'user' => $request->user(),
        ]);
    }
}
