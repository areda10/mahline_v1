<?php

declare(strict_types=1);

namespace App\Domains\Sales\Http\Controllers;

use App\Domains\Identity\Users\Models\User;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class SalesCartPageController
{
    public function show(Request $request): View
    {
        $user = $request->user();

        abort_if($user !== null && (! $user instanceof User || ! $user->hasRole('customer')), 403);

        return view('storefront.cart');
    }
}
