<?php

declare(strict_types=1);

namespace App\Domains\Identity\Notifications;

use App\Domains\Identity\Users\Models\User;
use App\Shared\Contracts\InventoryAlertNotificationDispatcher;
use Illuminate\Support\Facades\Notification;

final class InventoryStockAlertNotificationDispatcher implements InventoryAlertNotificationDispatcher
{
    /** @param array<string, mixed> $payload */
    public function dispatch(string $eventKey, array $payload): void
    {
        if ($eventKey !== 'inventory.stock_alert.triggered') {
            return;
        }

        $cooperativeId = (string) ($payload['cooperative_id'] ?? '');
        if ($cooperativeId === '') {
            return;
        }

        $recipients = User::query()
            ->where('status', 'active')
            ->whereNotNull('email_verified_at')
            ->whereNull('emergency_locked_at')
            ->where(function ($query) use ($cooperativeId): void {
                $query->where(function ($users) use ($cooperativeId): void {
                    $users->where('cooperative_id', $cooperativeId)
                        ->whereHas('cooperative', static fn ($cooperatives) => $cooperatives->where('status', 'active'))
                        ->whereHas('roles', static fn ($roles) => $roles
                            ->whereIn('slug', ['cooperative_admin', 'user'])
                            ->where('roles.is_active', true));
                })->orWhere(function ($admins) use ($cooperativeId): void {
                    $admins->whereHas('roles', static fn ($roles) => $roles
                        ->where('slug', 'super_admin')
                        ->where('roles.is_active', true))
                        ->whereHas('cooperatives', static fn ($cooperatives) => $cooperatives->whereKey($cooperativeId));
                });
            })
            ->get();

        if ($recipients->isNotEmpty()) {
            Notification::send($recipients, new InventoryStockAlertNotification($eventKey, $payload));
        }
    }
}
