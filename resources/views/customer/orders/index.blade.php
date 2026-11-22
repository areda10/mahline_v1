@extends('layouts.customer')

@section('title', 'Historique de mes commandes')

@push('styles')
    <style>
        .orders-toolbar{display:flex;gap:10px;flex-wrap:wrap;margin:16px 0}
        .orders-toolbar input,.orders-toolbar select{padding:10px 12px;border:1px solid #cbd8d1;border-radius:7px;background:#fff}
        .orders-toolbar button,.order-link{display:inline-block;padding:10px 14px;border-radius:7px;background:#1d5b49;color:#fff;text-decoration:none;border:0;cursor:pointer}
        .order-row{display:flex;justify-content:space-between;gap:16px;align-items:center;flex-wrap:wrap;padding:16px 0;border-bottom:1px solid #e7eee9}
        .order-row:last-child{border-bottom:0}
        .order-meta{display:flex;gap:16px;flex-wrap:wrap;color:#65736e;font-size:14px;margin-top:6px}
        .status-pill{display:inline-block;padding:5px 9px;border-radius:999px;background:#edf3ef;color:#25483b;font-size:13px}
        .empty-state{text-align:center;padding:36px 12px}
    </style>
@endpush

@section('content')
    <section class="card">
        <h1>Historique de mes commandes</h1>
        <p class="muted">Retrouvez vos commandes, leur état et le détail de chaque coopérative.</p>

        <form class="orders-toolbar" method="GET" action="{{ route('customer.orders.index') }}">
            <input type="search" name="q" value="{{ $filters['q'] ?? '' }}" maxlength="80" placeholder="Référence de commande">
            <select name="status" aria-label="Filtrer par statut">
                <option value="">Tous les statuts</option>
                @foreach ($statusLabels as $value => $label)
                    @if (in_array($value, ['awaiting_cooperatives','partially_confirmed','confirmed','partially_refused','refused','preparing','partially_shipped','shipped','completed','partially_cancelled','cancelled'], true))
                        <option value="{{ $value }}" @selected(($filters['status'] ?? '') === $value)>{{ $label }}</option>
                    @endif
                @endforeach
            </select>
            <button type="submit">Rechercher</button>
            @if (!empty($filters['q']) || !empty($filters['status']))
                <a href="{{ route('customer.orders.index') }}">Effacer</a>
            @endif
        </form>

        @forelse ($orders as $order)
            <article class="order-row">
                <div>
                    <strong>Commande {{ $order->reference }}</strong>
                    <div class="order-meta">
                        <span>{{ optional($order->placed_at ?? $order->created_at)->format('d/m/Y à H:i') }}</span>
                        <span>{{ $order->cooperative_orders_count }} coopérative(s)</span>
                        <span>{{ number_format((float) $order->grand_total, 2, ',', ' ') }} {{ $order->currency }}</span>
                    </div>
                    <p><span class="status-pill">{{ $statusLabels[$order->status->value] ?? $order->status->value }}</span></p>
                </div>
                <a class="order-link" href="{{ route('customer.orders.show', $order) }}">Voir le détail</a>
            </article>
        @empty
            <div class="empty-state">
                <h2>Aucune commande trouvée</h2>
                <p class="muted">Vos commandes apparaîtront ici après votre premier achat.</p>
                <a href="{{ route('storefront.products.index') }}">Découvrir la boutique</a>
            </div>
        @endforelse

        <div>{{ $orders->links() }}</div>
    </section>
@endsection
