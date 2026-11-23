@extends(auth()->user()->hasRole('super_admin_root') ? 'layouts.root' : 'layouts.admin')

@section('title', 'Boîte de réception des commandes')

@section('content')
@php($routePrefix = auth()->user()->hasRole('super_admin_root') ? 'root.backoffice' : 'backoffice')
<div class="page-header">
    <div>
        <h1>Commandes à traiter</h1>
        <p class="muted">Sous-commandes des coopératives accessibles à votre compte.</p>
    </div>
</div>

<div class="card">
    <form method="GET" action="{{ route($routePrefix.'.orders.cooperative.index') }}" class="form-grid">
        <label>
            Référence, client ou e-mail
            <input type="text" name="q" value="{{ request('q') }}" maxlength="100">
        </label>
        <label>
            Statut
            <select name="status">
                <option value="">Tous les statuts</option>
                @foreach($statuses as $status)
                    <option value="{{ $status->value }}" @selected(request('status') === $status->value)>
                        {{ ucfirst(str_replace('_', ' ', $status->value)) }}
                    </option>
                @endforeach
            </select>
        </label>
        <div class="actions">
            <button class="button" type="submit">Filtrer</button>
            <a class="button button-secondary" href="{{ route($routePrefix.'.orders.cooperative.index') }}">Réinitialiser</a>
        </div>
    </form>
</div>

<div class="card">
    @if($suborders->isEmpty())
        <div class="empty">Aucune sous-commande ne correspond aux filtres.</div>
    @else
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Sous-commande</th>
                        <th>Commande</th>
                        <th>Client</th>
                        <th>Coopérative</th>
                        <th>Statut</th>
                        <th>Échéance de réponse</th>
                        <th>Total produits</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($suborders as $suborder)
                        <tr>
                            <td><strong>{{ $suborder->reference }}</strong></td>
                            <td>{{ $suborder->order?->reference }}</td>
                            <td>
                                {{ $suborder->order?->customer?->display_name ?? 'Client' }}
                                <div class="muted">{{ $suborder->order?->customer?->email }}</div>
                            </td>
                            <td>{{ $suborder->cooperative?->name }}</td>
                            <td><span class="badge">{{ ucfirst(str_replace('_', ' ', $suborder->status->value)) }}</span></td>
                            <td>
                                {{ $suborder->response_deadline_at?->format('d/m/Y H:i') ?? 'Non définie' }}
                                @if($suborder->response_overdue_at)
                                    <div class="muted">Délai dépassé</div>
                                @endif
                            </td>
                            <td>{{ number_format((float) $suborder->items_subtotal, 2, ',', ' ') }} MAD</td>
                            <td>
                                <a class="button button-small" href="{{ route($routePrefix.'.orders.cooperative.show', $suborder) }}">Détail</a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="pagination">{{ $suborders->links() }}</div>
    @endif
</div>
@endsection
