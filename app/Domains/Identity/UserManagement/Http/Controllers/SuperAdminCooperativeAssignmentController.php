<?php

declare(strict_types=1);

namespace App\Domains\Identity\UserManagement\Http\Controllers;

use App\Domains\Cooperatives\Models\Cooperative;
use App\Domains\Identity\UserManagement\Services\UserManagementService;
use App\Domains\Identity\Users\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Illuminate\Pagination\LengthAwarePaginator;

final class SuperAdminCooperativeAssignmentController
{
    public function __construct(
        private readonly UserManagementService $userManagementService,
    ) {
    }

    public function index(User $superAdmin): View
    {
        Gate::authorize('viewAssignments', $superAdmin);

        $cooperatives = $superAdmin->cooperatives()
            ->with('address.country')
            ->orderBy('cooperatives.name')
            ->paginate(15, ['*'], 'assigned_page');

        $availableCooperatives = $this->isSuperAdminRoot(request()->user())
            ? Cooperative::query()
                ->with('address.country')
                ->whereDoesntHave(
                    'superAdmins',
                    static fn (Builder $query) => $query->whereKey($superAdmin->getKey()),
                )
                ->orderBy('name')
                ->paginate(15, ['*'], 'available_page')
            : new LengthAwarePaginator(
                collect(),
                0,
                15,
                1,
                [
                    'path' => request()->url(),
                    'pageName' => 'available_page',
                ],
            );

        return view('backoffice.super-admins.cooperatives.index', [
            'superAdmin' => $superAdmin,
            'cooperatives' => $cooperatives,
            'availableCooperatives' => $availableCooperatives,
        ]);
    }

    public function store(
        Request $request,
        User $superAdmin,
        Cooperative $cooperative,
    ): RedirectResponse {
        Gate::authorize('assignCooperative', [$superAdmin, $cooperative]);

        try {
            $this->userManagementService->assignSuperAdminToCooperative(
                $request->user(),
                $superAdmin,
                $cooperative,
            );
        } catch (\DomainException) {
            return redirect()
                ->route('backoffice.super-admins.cooperatives.index', $superAdmin)
                ->with('error', 'Cette affectation n’est plus autorisée. Actualisez la page et vérifiez le statut du super administrateur.');
        } catch (QueryException $exception) {
            report($exception);

            return redirect()
                ->route('backoffice.super-admins.cooperatives.index', $superAdmin)
                ->with('error', 'Un conflit de base de données a empêché l’affectation. Aucune modification partielle n’a été conservée.');
        }

        return redirect()
            ->route('backoffice.super-admins.cooperatives.index', $superAdmin)
            ->with('success', 'La coopérative a été affectée au super administrateur.');
    }

    public function destroy(
        Request $request,
        User $superAdmin,
        Cooperative $cooperative,
    ): RedirectResponse {
        Gate::authorize('unassignCooperative', [$superAdmin, $cooperative]);

        try {
            $this->userManagementService->removeSuperAdminFromCooperative(
                $request->user(),
                $superAdmin,
                $cooperative,
            );
        } catch (\DomainException) {
            return redirect()
                ->route('backoffice.super-admins.cooperatives.index', $superAdmin)
                ->with('error', 'Cette désaffectation n’est plus autorisée. Actualisez la page et vérifiez le statut du super administrateur.');
        } catch (QueryException $exception) {
            report($exception);

            return redirect()
                ->route('backoffice.super-admins.cooperatives.index', $superAdmin)
                ->with('error', 'Un conflit de base de données a empêché la désaffectation. Aucune modification partielle n’a été conservée.');
        }

        return redirect()
            ->route('backoffice.super-admins.cooperatives.index', $superAdmin)
            ->with('success', 'L’affectation de la coopérative a été retirée.');
    }

    private function isSuperAdminRoot(?User $user): bool
    {
        return $user !== null
            && $user->isAuthorized()
            && $user->roles()
                ->where('slug', 'super_admin_root')
                ->exists();
    }
}
