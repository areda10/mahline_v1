<?php

declare(strict_types=1);

namespace App\Domains\Identity\SuperAdminRoot\Http\Controllers;

use App\Domains\Audit\Models\AuditEvent;
use App\Domains\Catalog\Models\Catalog;
use App\Domains\Categories\Models\Category;
use App\Domains\Cooperatives\Enums\CooperativeStatus;
use App\Domains\Cooperatives\Models\Cooperative;
use App\Domains\Identity\Enums\UserStatus;
use App\Domains\Identity\Users\Models\User;
use App\Domains\Products\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

final class DashboardController
{
    public function __invoke(Request $request): View
    {
        abort_unless($request->user()?->hasRole('super_admin_root'), 403);

        $cooperativeStatuses = $this->countByStatus(Cooperative::class, CooperativeStatus::cases());
        $userStatuses = $this->countByStatus(User::class, UserStatus::cases());

        $usersByRole = collect(['super_admin_root', 'super_admin', 'cooperative_admin', 'user'])
            ->mapWithKeys(fn (string $role): array => [
                $role => User::query()->whereHas(
                    'roles',
                    fn ($query) => $query->where('slug', $role),
                )->count(),
            ]);

        return view('super-admin-root.dashboard', [
            'cooperativeTotal' => Cooperative::query()->count(),
            'cooperativeStatuses' => $cooperativeStatuses,
            'userTotal' => User::query()->count(),
            'userStatuses' => $userStatuses,
            'unverifiedUsers' => User::query()->whereNull('email_verified_at')->count(),
            'usersByRole' => $usersByRole,
            'catalogTotal' => Catalog::query()->count(),
            'activeCatalogTotal' => Catalog::query()->where('is_active', true)->count(),
            'productTotal' => Product::query()->count(),
            'activeProductTotal' => Product::query()->where('is_active', true)->count(),
            'categoryTotal' => Category::query()->count(),
            'globalCategoryTotal' => Category::query()->whereNull('cooperative_id')->count(),
            'localCategoryTotal' => Category::query()->whereNotNull('cooperative_id')->count(),
            'recentActivities' => AuditEvent::query()
                ->orderByDesc('occurred_at')
                ->limit(8)
                ->get(['id', 'event_key', 'actor_id', 'subject_id', 'occurred_at']),
        ]);
    }

    /**
     * @param class-string<\Illuminate\Database\Eloquent\Model> $model
     * @param array<int, \BackedEnum> $statuses
     * @return Collection<string, int>
     */
    private function countByStatus(string $model, array $statuses): Collection
    {
        return collect($statuses)->mapWithKeys(
            fn (\BackedEnum $status): array => [
                (string) $status->value => $model::query()
                    ->where('status', $status->value)
                    ->count(),
            ],
        );
    }
}
