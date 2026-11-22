<?php

declare(strict_types=1);

namespace App\Domains\Notifications\Http\Controllers;

use App\Core\Foundation\Http\Controllers\BaseController;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

final class NotificationCenterController extends BaseController
{
    public function index(): View
    {
        $user = Auth::user();
        $notifications = $user->notifications()->latest()->paginate(20);

        return view('notifications.index', [
            'notifications' => $notifications,
            'unreadCount' => $user->unreadNotifications()->count(),
        ]);
    }

    public function markRead(string $notificationId): RedirectResponse
    {
        $notification = Auth::user()->notifications()
            ->whereKey($notificationId)
            ->firstOrFail();

        if ($notification->read_at === null) {
            $notification->markAsRead();
        }

        return back()->with('success', 'Notification marquée comme lue.');
    }

    public function markAllRead(): RedirectResponse
    {
        Auth::user()->unreadNotifications()->update([
            'read_at' => now(),
        ]);

        return back()->with('success', 'Toutes les notifications ont été marquées comme lues.');
    }
}
