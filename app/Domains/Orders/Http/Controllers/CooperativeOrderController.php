<?php

declare(strict_types=1);

namespace App\Domains\Orders\Http\Controllers;

use App\Domains\Identity\Users\Models\User;
use App\Domains\Orders\Enums\CooperativeOrderStatus;
use App\Domains\Orders\Models\CooperativeOrder;
use App\Domains\Orders\Services\CooperativeOrderResponseService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

final class CooperativeOrderController
{
    public function __construct(
        private readonly CooperativeOrderResponseService $responses,
    ) {}

    public function index(Request $request): View
    {
        $data = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'string', Rule::in(array_map(
                static fn (CooperativeOrderStatus $status): string => $status->value,
                CooperativeOrderStatus::cases(),
            ))],
        ]);

        $query = $this->visibleSuborders($request->user());
        if (($data['q'] ?? '') !== '') {
            $term = trim($data['q']);
            $query->where(static function (Builder $builder) use ($term): void {
                $builder->where('reference', 'like', '%'.$term.'%')
                    ->orWhereHas('order', static fn (Builder $order): Builder => $order->where('reference', 'like', '%'.$term.'%'))
                    ->orWhereHas('order.customer', static fn (Builder $customer): Builder => $customer
                        ->where('display_name', 'like', '%'.$term.'%')
                        ->orWhere('email', 'like', '%'.$term.'%'));
            });
        }

        if (($data['status'] ?? '') !== '') {
            $query->where('status', $data['status']);
        }

        return view('orders.cooperative.index', [
            'suborders' => $query->latest('created_at')->paginate(20)->withQueryString(),
            'statuses' => CooperativeOrderStatus::cases(),
        ]);
    }

    public function show(Request $request, string $cooperativeOrderId): View
    {
        $suborder = $this->visibleSuborders($request->user())
            ->whereKey($cooperativeOrderId)
            ->firstOrFail();

        return view('orders.cooperative.show', [
            'suborder' => $suborder,
            'routePrefix' => $request->user()->hasRole('super_admin_root') ? 'root.backoffice' : 'backoffice',
        ]);
    }

    public function confirm(Request $request, string $cooperativeOrderId)
    {
        $suborder = $this->visibleSuborders($request->user())->whereKey($cooperativeOrderId)->firstOrFail();
        $this->responses->confirmByCooperative($suborder, $request->user());

        return back()->with('success', 'La sous-commande a été confirmée et le stock a été réservé.');
    }

    public function refuse(Request $request, string $cooperativeOrderId)
    {
        $data = $request->validate([
            'reason' => ['required', 'string', 'min:3', 'max:2000'],
        ]);
        $suborder = $this->visibleSuborders($request->user())->whereKey($cooperativeOrderId)->firstOrFail();
        $this->responses->refuseByCooperative($suborder, $request->user(), $data['reason']);

        return back()->with('success', 'La sous-commande a été refusée.');
    }

    public function administrativeDecision(Request $request, string $cooperativeOrderId)
    {
        $data = $request->validate([
            'accept' => ['required', 'boolean'],
            'reason' => ['required', 'string', 'min:3', 'max:2000'],
        ]);
        $suborder = $this->visibleSuborders($request->user())->whereKey($cooperativeOrderId)->firstOrFail();
        $this->responses->decideOnBehalf(
            $suborder,
            $request->user(),
            (bool) $data['accept'],
            $data['reason'],
        );

        return back()->with('success', 'La décision administrative a été enregistrée.');
    }

    public function extendDeadline(Request $request, string $cooperativeOrderId)
    {
        $data = $request->validate([
            'response_deadline_at' => ['required', 'date', 'after:now'],
            'reason' => ['required', 'string', 'min:3', 'max:2000'],
        ]);
        $suborder = $this->visibleSuborders($request->user())->whereKey($cooperativeOrderId)->firstOrFail();
        $this->responses->extendResponseDeadline(
            $suborder,
            $request->user(),
            Carbon::parse($data['response_deadline_at']),
            $data['reason'],
        );

        return back()->with('success', 'Le délai de réponse a été prolongé.');
    }

    private function visibleSuborders(User $actor): Builder
    {
        $query = CooperativeOrder::query()->with([
            'cooperative',
            'order.customer',
            'items',
            'statusHistories.actor',
        ]);

        if ($actor->hasRole('super_admin_root')) {
            return $query;
        }

        if ($actor->hasRole('super_admin')) {
            return $query->whereIn(
                'cooperative_id',
                $actor->cooperatives()->select('cooperatives.id'),
            );
        }

        if ($actor->hasRole('cooperative_admin') && $actor->cooperative_id !== null) {
            return $query->where('cooperative_id', (string) $actor->cooperative_id);
        }

        abort(403);
    }
}
