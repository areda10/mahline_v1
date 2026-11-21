@extends('layouts.admin')

@section('title', 'Inventaire')

@section('content')
    <div class="page-header">
        <div>
            <h1>Inventaire</h1>
            <p class="muted">État des stocks et suivi des inventaires physiques dans votre périmètre.</p>
        </div>
        <div class="actions">
            @foreach($cooperativeIds as $cooperativeId)
                <a class="button button-secondary button-small"
                   href="{{ route('inventory.reports.pdf', ['cooperativeId' => $cooperativeId]) }}">
                    Rapport PDF · {{ substr($cooperativeId, 0, 8) }}
                </a>
            @endforeach
        </div>
    </div>

    <section class="card">
        <div class="page-header">
            <div>
                <h2>État des stocks</h2>
                <p class="muted">Seuls les états « En stock » et « Rupture de stock » sont affichés. Les quantités internes ne sont pas exposées ici.</p>
            </div>
        </div>

        @if($stocks === [])
            <p class="empty">Aucun stock n'est encore enregistré dans votre périmètre.</p>
        @else
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Coopérative</th>
                            <th>Type</th>
                            <th>Produit / variante</th>
                            <th>Unité</th>
                            <th>Disponibilité</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($stocks as $stock)
                            <tr>
                                <td><code>{{ $stock['cooperative_id'] }}</code></td>
                                <td>{{ $stock['stockable_type'] === 'variant' ? 'Variante' : 'Produit' }}</td>
                                <td><code>{{ $stock['stockable_id'] }}</code></td>
                                <td>{{ $stock['unit_code'] }}</td>
                                <td>
                                    <span class="badge {{ $stock['status'] === 'En stock' ? '' : 'badge-inactive' }}">
                                        {{ $stock['status'] }}
                                    </span>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>

    <section class="card">
        <h2>Inventaires physiques</h2>
        @if($countSessions === [])
            <p class="empty">Aucune session d'inventaire physique n'est enregistrée dans votre périmètre.</p>
        @else
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Coopérative</th>
                            <th>Session</th>
                            <th>Statut</th>
                            <th>Éléments comptés</th>
                            <th>Éléments totaux</th>
                            <th>Démarrée le</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($countSessions as $session)
                            <tr>
                                <td><code>{{ $session['cooperative_id'] }}</code></td>
                                <td><code>{{ $session['id'] }}</code></td>
                                <td><span class="badge">{{ str($session['status'])->replace('_', ' ')->title() }}</span></td>
                                <td>{{ $session['counted_items_count'] }}</td>
                                <td>{{ $session['items_count'] }}</td>
                                <td>{{ $session['started_at'] ? \Illuminate\Support\Carbon::parse($session['started_at'])->format('d/m/Y H:i') : '—' }}</td>
                                <td>
                                    <a class="button button-secondary button-small"
                                       href="{{ route('backoffice.inventory.count-sessions.show', ['sessionId' => $session['id']]) }}">
                                        Détails JSON
                                    </a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>
@endsection
