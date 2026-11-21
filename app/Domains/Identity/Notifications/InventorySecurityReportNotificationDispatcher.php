<?php

declare(strict_types=1);

namespace App\Domains\Identity\Notifications;

use App\Domains\Identity\Users\Models\User;
use App\Shared\Contracts\InventorySecurityReportNotificationDispatcher as DispatcherContract;
use Illuminate\Support\Facades\Notification;

final class InventorySecurityReportNotificationDispatcher implements DispatcherContract
{
    /** @param array<string, mixed> $payload */
    public function dispatch(string $eventKey, array $payload): void
    {
        if (! in_array($eventKey, [
            'inventory.security_report.created',
            'inventory.security_report.escalated',
            'inventory.security_report.closed',
            'inventory.security_report.reopened',
        ], true)) {
            return;
        }

        $cooperativeId = (string) ($payload['cooperative_id'] ?? '');
        if ($cooperativeId === '') {
            return;
        }

        $recipients = User::query()
            ->where('status', 'active')
            ->where(function ($query) use ($cooperativeId): void {
                $query->whereHas('roles', static fn ($roles) => $roles->where('slug', 'super_admin_root'))
                    ->orWhere(function ($scoped) use ($cooperativeId): void {
                        $scoped->where('cooperative_id', $cooperativeId)
                            ->whereHas('roles', static fn ($roles) => $roles->where('slug', 'cooperative_admin'));
                    })
                    ->orWhere(function ($assigned) use ($cooperativeId): void {
                        $assigned->whereHas('roles', static fn ($roles) => $roles->where('slug', 'super_admin'))
                            ->whereHas('cooperatives', static fn ($cooperatives) => $cooperatives->whereKey($cooperativeId));
                    });
            })
            ->distinct()
            ->get();

        if ($recipients->isNotEmpty()) {
            Notification::send($recipients, new InventorySecurityReportNotification($eventKey, $payload));
        }
    }
}
