<?php

declare(strict_types=1);

namespace App\Domains\Dashboard\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\View\View;

final class UserDashboardController
{
    public function __invoke(Request $request): View
    {
        $user = $request->user();

        abort_unless($user?->hasRole('user'), 403);

        return view('user.dashboard', [
            'user' => $user->loadMissing('cooperative'),
        ]);
    }
}
