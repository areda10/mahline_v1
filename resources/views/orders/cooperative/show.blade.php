@extends(auth()->user()->hasRole('super_admin_root') ? 'layouts.root' : 'layouts.admin')

@section('title', 'Détail de la sous-commande')

@section('content')
@php
    $isRoot = auth()->user()->hasRole('super_admin_root');
    $isAdmin = auth()->user()->hasAnyRole(['super_admin_root', 'super_admin']);
    $isAwaiting = $suborder->status === \App\Domains\Orders\Enums\CooperativeOrderStatus::AWAITING_COOPERATIVE;
    $deadlineOpen = $suborder->response_deadline_at !== null && $suborder->response_deadline_at->isFuture();
    $routeBase = $routePrefix.'.orders.cooperative';
    $address = $suborder->order?->delivery_address_snapshot ?? [];
    $fixedMaximum = $suborder->created_at?->copy()->addHours(24);
@endphp

<div class="page-header">
    <div>
        <h1>Sous-commande {{ $suborder->reference }}</h1>
        <p class="muted">Commande {{ $suborder->order?->reference }} — {{ $suborder->cooperative?->name }}</p>
    </div>
    <a class="button button-secondary" href="{{ route($routeBase.'.index') }}">Retour à la boîte de réception</a>
</div>

@if($errors->any())
    <div class="alert alert-error" role="alert">
        <ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
    </div>
@endif

<div class="card">
    <h2>Résumé</h2>
    <dl>
        <dt>Statut</dt><dd>{{ ucfirst(str_replace('_', ' ', $suborder->status->value)) }}</dd>
        <dt>Client</dt><dd>{{ $suborder->order?->customer?->display_name }} — {{ $suborder->order?->customer?->email }}</dd>
        <dt>Créée le</dt><dd>{{ $suborder->created_at?->format('d/m/Y H:i') }}</dd>
        <dt>Réponse attendue avant</dt><dd>{{ $suborder->response_deadline_at?->format('d/m/Y H:i') ?? 'Non définie' }}</dd>
        <dt>Réponse enregistrée le</dt><dd>{{ $suborder->responded_at?->format('d/m/Y H:i') ?? 'En attente' }}</dd>
        <dt>Sous-total produits</dt><dd>{{ number_format((float) $suborder->items_subtotal, 2, ',', ' ') }} MAD</dd>
        <dt>Frais de livraison enregistrés</dt><dd>{{ number_format((float) $suborder->delivery_fee, 2, ',', ' ') }} MAD</dd>
        <dt>Total enregistré</dt><dd>{{ number_format((float) $suborder->grand_total, 2, ',', ' ') }} MAD</dd>
        @if($suborder->refusal_reason)
            <dt>Motif du refus</dt><dd>{{ $suborder->refusal_reason }}</dd>
        @endif
        @if($suborder->substituted_by_admin_id)
            <dt>Intervention administrative</dt><dd>{{ $suborder->substitutedByAdmin?->display_name ?? 'Administrateur' }} — {{ $suborder->substitution_reason }}</dd>
        @endif
    </dl>
</div>

<div class="card">
    <h2>Articles</h2>
    <div class="table-wrap">
        <table>
            <thead><tr><th>Article</th><th>Quantité</th><th>Prix unitaire</th><th>Total ligne</th></tr></thead>
            <tbody>
                @foreach($suborder->items as $item)
                    @php($snapshot = $item->product_snapshot ?? [])
                    <tr>
                        <td>
                            {{ $snapshot['product_name'] ?? 'Article' }}
                            @if(!empty($snapshot['variant_label']))
                                <div class="muted">{{ $snapshot['variant_label'] }}</div>
                            @endif
                        </td>
                        <td>{{ $item->quantity }} {{ $snapshot['unit_label'] ?? $item->unit }}</td>
                        <td>{{ number_format((float) $item->unit_price, 2, ',', ' ') }} MAD</td>
                        <td>{{ number_format((float) $item->line_total, 2, ',', ' ') }} MAD</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>

<div class="card">
    <h2>Adresse de livraison enregistrée</h2>
    <p>
        {{ $address['address'] ?? '' }}
        {{ $address['address_line_2'] ?? '' }}<br>
        {{ $address['postal_code'] ?? '' }} {{ $address['city'] ?? '' }}<br>
        {{ $address['region'] ?? '' }} {{ $address['country'] ?? '' }}
    </p>
</div>

@if($isAwaiting)
    <div class="card">
        <h2>Réponse de la coopérative</h2>
        @if($deadlineOpen)
            <p>La réponse doit être enregistrée avant le {{ $suborder->response_deadline_at->format('d/m/Y à H:i') }}.</p>
            <div class="form-grid">
                <form method="POST" action="{{ route($routeBase.'.confirm', $suborder) }}">
                    @csrf
                    <button class="button" type="submit">Confirmer et réserver le stock</button>
                </form>
                <form method="POST" action="{{ route($routeBase.'.refuse', $suborder) }}">
                    @csrf
                    <label>Motif obligatoire du refus
                        <textarea name="reason" required minlength="3" maxlength="2000">{{ old('reason') }}</textarea>
                    </label>
                    <button class="button button-danger" type="submit">Refuser la sous-commande</button>
                </form>
            </div>
        @else
            <p class="muted">Le délai de réponse est dépassé. La confirmation ou le refus par la coopérative n'est plus disponible.</p>
        @endif
    </div>

    @if($isAdmin && $deadlineOpen)
        <div class="card">
            <h2>Intervention administrative</h2>
            <p class="muted">Cette action remplace la réponse de la coopérative et exige un motif. Elle reste possible uniquement avant l'expiration du délai.</p>
            <div class="form-grid">
                <form method="POST" action="{{ route($routeBase.'.administrative-decision', $suborder) }}">
                    @csrf
                    <input type="hidden" name="accept" value="1">
                    <label>Motif de la confirmation administrative
                        <textarea name="reason" required minlength="3" maxlength="2000"></textarea>
                    </label>
                    <button class="button" type="submit">Confirmer administrativement</button>
                </form>
                <form method="POST" action="{{ route($routeBase.'.administrative-decision', $suborder) }}">
                    @csrf
                    <input type="hidden" name="accept" value="0">
                    <label>Motif du refus administratif
                        <textarea name="reason" required minlength="3" maxlength="2000"></textarea>
                    </label>
                    <button class="button button-danger" type="submit">Refuser administrativement</button>
                </form>
            </div>
        </div>
    @endif

    @if($isAdmin && $fixedMaximum && $fixedMaximum->isFuture())
        <div class="card">
            <h2>Prolonger le délai</h2>
            <p class="muted">La nouvelle échéance doit être postérieure à l'échéance actuelle et ne peut pas dépasser {{ $fixedMaximum->format('d/m/Y H:i') }} (fenêtre maximale de 24 heures à compter de la création).</p>
            <form method="POST" action="{{ route($routeBase.'.extend-deadline', $suborder) }}">
                @csrf
                <div class="form-grid">
                    <label>Nouvelle échéance
                        <input type="datetime-local" name="response_deadline_at" required
                            min="{{ now()->format('Y-m-d\TH:i') }}"
                            max="{{ $fixedMaximum->format('Y-m-d\TH:i') }}"
                            value="{{ old('response_deadline_at') }}">
                    </label>
                    <label>Motif obligatoire
                        <textarea name="reason" required minlength="3" maxlength="2000">{{ old('reason') }}</textarea>
                    </label>
                </div>
                <button class="button" type="submit">Enregistrer la prolongation</button>
            </form>
        </div>
    @endif
@endif

<div class="card">
    <h2>Historique des décisions</h2>
    @forelse($suborder->statusHistories as $history)
        <div style="padding:12px 0;border-bottom:1px solid #e5eaed">
            <strong>{{ $history->from_status ?? 'Création' }} → {{ $history->to_status }}</strong>
            <div class="muted">{{ $history->occurred_at?->format('d/m/Y H:i') }} — {{ $history->actor_role ?? 'système' }}</div>
            @if($history->reason)<p>{{ $history->reason }}</p>@endif
        </div>
    @empty
        <p class="muted">Aucun événement historisé.</p>
    @endforelse
</div>
@endsection
