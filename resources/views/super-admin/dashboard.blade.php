@extends('layouts.admin')

@section('title', 'Tableau de bord — Super Admin')

@section('content')
    <div class="page-header">
        <div>
            <h1>Tableau de bord</h1>
            <p class="muted">Super Admin · Vue limitée aux coopératives auxquelles votre compte a accès</p>
        </div>
    </div>

    <section class="dashboard-grid" aria-label="Indicateurs">
        <article class="card dashboard-card"><span class="muted">Coopératives accessibles</span><strong>{{ $cooperativeTotal }}</strong></article>
        <article class="card dashboard-card"><span class="muted">Utilisateurs</span><strong>{{ $userTotal }}</strong><small>Adresses à vérifier : {{ $unverifiedUsers }}</small></article>
        <article class="card dashboard-card"><span class="muted">Catalogues</span><strong>{{ $catalogTotal }}</strong><small>Actifs : {{ $activeCatalogTotal }}</small></article>
        <article class="card dashboard-card"><span class="muted">Produits</span><strong>{{ $productTotal }}</strong><small>Actifs : {{ $activeProductTotal }}</small></article>
        <article class="card dashboard-card"><span class="muted">Catégories disponibles</span><strong>{{ $categoryTotal }}</strong><small>Actives : {{ $activeCategoryTotal }}</small></article>
    </section>

    <section class="card">
        <h2>Mes coopératives accessibles</h2>
        @if($cooperatives->isEmpty())
            <p class="empty">Aucune coopérative n'est actuellement associée à votre compte.</p>
        @else
            <div class="table-wrap">
                <table>
                    <thead><tr><th>Coopérative</th><th>Statut</th></tr></thead>
                    <tbody>
                        @foreach($cooperatives as $cooperative)
                            <tr><td>{{ $cooperative->name }}</td><td>{{ $cooperative->status->value }}</td></tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>
@endsection

@push('styles')
<style>
.dashboard-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(190px,1fr));gap:14px}
.dashboard-card{display:flex;flex-direction:column;gap:8px;margin:0;min-width:0}
.dashboard-card strong{font-size:30px;color:#153d34}
.dashboard-card small{color:#65737e}
</style>
@endpush
