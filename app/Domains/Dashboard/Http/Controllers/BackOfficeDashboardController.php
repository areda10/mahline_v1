<?php

declare(strict_types=1);

namespace App\Domains\Dashboard\Http\Controllers;

use App\Domains\Catalog\Models\Catalog;
use App\Domains\Categories\Models\Category;
use App\Domains\Cooperatives\Enums\CooperativeStatus;
use App\Domains\Cooperatives\Models\Cooperative;
use App\Domains\Identity\Users\Models\User;
use App\Domains\Products\Models\Product;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

final class BackOfficeDashboardController
{
    public function __invoke(Request $request): View
    {
        $user = $request->user();

        abort_unless(
            $user !== null && $user->hasAnyRole(['super_admin', 'cooperative_admin']),
            403,
        );

        $cooperatives = Cooperative::query()
            ->visibleTo($user)
            ->get(['id', 'name', 'status']);

        $cooperativeIds = $cooperatives->modelKeys();

        $cooperativeQuery = Cooperative::query()->whereIn('id', $cooperativeIds);
        $userQuery = User::query()->whereIn('cooperative_id', $cooperativeIds);
        $catalogQuery = Catalog::query()->whereIn('cooperative_id', $cooperativeIds);
        $productQuery = Product::query()->whereIn('cooperative_id', $cooperativeIds);
        $categoryQuery = Category::query()->where(function ($query) use ($cooperativeIds): void {
            $query->whereNull('cooperative_id')
                ->orWhereIn('cooperative_id', $cooperativeIds);
        });

        $cooperativeStatuses = $this->countByStatus(
            (clone $cooperativeQuery),
            CooperativeStatus::cases(),
        );

        $isSuperAdmin = $user->hasRole('super_admin');

        return view(
            $isSuperAdmin ? 'super-admin.dashboard' : 'cooperative-admin.dashboard',
            [
                'user' => $user,
                'cooperatives' => $cooperatives,
                'cooperativeTotal' => $cooperativeQuery->count(),
                'cooperativeStatuses' => $cooperativeStatuses,
                'userTotal' => $userQuery->count(),
                'unverifiedUsers' => (clone $userQuery)->whereNull('email_verified_at')->count(),
                'catalogTotal' => $catalogQuery->count(),
                'activeCatalogTotal' => (clone $catalogQuery)->where('is_active', true)->count(),
                'productTotal' => $productQuery->count(),
                'activeProductTotal' => (clone $productQuery)->where('is_active', true)->count(),
                'categoryTotal' => $categoryQuery->count(),
                'activeCategoryTotal' => (clone $categoryQuery)->where('is_active', true)->count(),
            ],
        );
    }

    /**
     * @param  Builder  $query
     * @param  array<int, \BackedEnum>  $statuses
     * @return Collection<string, int>
     */
    private function countByStatus($query, array $statuses): Collection
    {
        return collect($statuses)->mapWithKeys(
            fn (\BackedEnum $status): array => [
                (string) $status->value => (clone $query)
                    ->where('status', $status->value)
                    ->count(),
            ],
        );
    }
}
