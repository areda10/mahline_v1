@extends('layouts.customer')

@section('title', 'Commande '.$order->reference)

@push('styles')
    <style>
        .order-heading{display:flex;justify-content:space-between;gap:16px;align-items:flex-start;flex-wrap:wrap}
        .status-pill{display:inline-block;padding:5px 9px;border-radius:999px;background:#edf3ef;color:#25483b;font-size:13px}
        .suborder{border:1px solid #e0e8e3;border-radius:9px;padding:18px;margin:16px 0}
        .suborder-head,.item-row,.total-row{display:flex;justify-content:space-between;gap:14px;flex-wrap:wrap}
        .item-row{padding:12px 0;border-bottom:1px solid #edf1ee}
        .item-row:last-child{border-bottom:0}
        .muted{color:#65736e}
        .timeline{border-left:2px solid #d9e5de;margin:14px 0 0 6px;padding-left:18px}
        .timeline-entry{padding:0 0 14px;position:relative}
        .timeline-entry:before{content:"";position:absolute;left:-24px;top:5px;width:10px;height:10px;border-radius:50%;background:#43856d}
        .address{white-space:pre-line}
    </style>
@endpush

@section('content')
    <section class="card">
        <div class="order-heading">
            <div>
                <p><a href="{{ route('customer.orders.index') }}">← Mes commandes</a></p>
                <h1>Commande {{ $order->reference }}</h1>
                <p class="muted">Passée le {{ optional($order->placed_at ?? $order->created_at)->format('d/m/Y à H:i') }}</p>
            </div>
            <span class="status-pill">{{ $statusLabels[$order->status->value] ?? $order->status->value }}</span>
        </div>
        <div class="total-row">
            <span>Sous-total des articles</span><strong>{{ number_format((float) $order->items_subtotal, 2, ',', ' ') }} {{ $order->currency }}</strong>
        </div>
        <div class="total-row">
            <span>Livraison</span><strong>{{ number_format((float) $order->delivery_total, 2, ',', ' ') }} {{ $order->currency }}</strong>
        </div>
        <div class="total-row">
            <span>Total de la commande</span><strong>{{ number_format((float) $order->grand_total, 2, ',', ' ') }} {{ $order->currency }}</strong>
        </div>
    </section>

    @foreach ($order->cooperativeOrders as $suborder)
        <section class="card">
            <div class="suborder-head">
                <div>
                    <h2>{{ $suborder->cooperative?->name ?? 'Coopérative' }}</h2>
                    <p class="muted">Sous-commande {{ $suborder->reference }}</p>
                </div>
                <span class="status-pill">{{ $statusLabels[$suborder->status->value] ?? $suborder->status->value }}</span>
            </div>
            <h3>Articles</h3>
            @foreach ($suborder->items as $item)
                <div class="item-row">
                    <div>
                        <strong>{{ $item->product_snapshot['name'] ?? $item->product_snapshot['product_name'] ?? 'Article' }}</strong>
                        @if (!empty($item->product_snapshot['variant_name']))
                            <div class="muted">{{ $item->product_snapshot['variant_name'] }}</div>
                        @endif
                        <div class="muted">{{ number_format((float) $item->quantity, 3, ',', ' ') }} {{ $item->unit }}</div>
                        <div class="muted">État : {{ $statusLabels[$item->status->value] ?? $item->status->value }}</div>
                    </div>
                    <strong>{{ number_format((float) $item->line_total, 2, ',', ' ') }} {{ $order->currency }}</strong>
                </div>
            @endforeach
            <div class="total-row">
                <span>Total de cette coopérative</span>
                <strong>{{ number_format((float) $suborder->grand_total, 2, ',', ' ') }} {{ $order->currency }}</strong>
            </div>

            @if ($suborder->statusHistories->isNotEmpty())
                <h3>Suivi de la coopérative</h3>
                <div class="timeline">
                    @foreach ($suborder->statusHistories as $event)
                        <div class="timeline-entry">
                            <strong>{{ $statusLabels[$event->to_status] ?? $event->to_status }}</strong>
                            <div class="muted">{{ $event->occurred_at?->format('d/m/Y à H:i') }}</div>
                            @if ($event->reason)
                                <p>{{ $event->reason }}</p>
                            @endif
                        </div>
                    @endforeach
                </div>
            @endif
        </section>
    @endforeach

    @if (is_array($order->delivery_address_snapshot))
        <section class="card">
            <h2>Adresse de livraison enregistrée</h2>
            <p class="address">{{ collect($order->delivery_address_snapshot)->filter(fn ($value) => is_scalar($value) && trim((string) $value) !== '')->values()->implode("\n") }}</p>
        </section>
    @endif

    @if ($order->statusHistories->isNotEmpty())
        <section class="card">
            <h2>Historique de la commande</h2>
            <div class="timeline">
                @foreach ($order->statusHistories as $event)
                    <div class="timeline-entry">
                        <strong>{{ $statusLabels[$event->to_status] ?? $event->to_status }}</strong>
                        <div class="muted">{{ $event->occurred_at?->format('d/m/Y à H:i') }}</div>
                        @if ($event->reason)
                            <p>{{ $event->reason }}</p>
                        @endif
                    </div>
                @endforeach
            </div>
        </section>
    @endif
@endsection
