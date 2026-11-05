@extends('layouts.admin')

@section('title', 'Tableau de bord Super Admin Root')

@section('content')
    <div class="page-header">
        <div><h1>Tableau de bord</h1><p class="muted">Vue globale de la plateforme MAHLINE</p></div>
        <div class="actions">
            <a class="button" href="{{ route('backoffice.cooperatives.index') }}">Gérer les coopératives</a>
            <a class="button button-secondary" href="{{ route('backoffice.users.index') }}">Gérer les utilisateurs</a>
        </div>
    </div>
    <section class="dashboard-grid" aria-label="Indicateurs globaux">
        <article class="card dashboard-card"><span class="muted">Coopératives</span><strong>{{ $cooperativeTotal }}</strong><small>Actives : {{ $cooperativeStatuses['active'] ?? 0 }}</small></article>
        <article class="card dashboard-card"><span class="muted">Utilisateurs</span><strong>{{ $userTotal }}</strong><small>Adresses e-mail à vérifier : {{ $unverifiedUsers }}</small></article>
        <article class="card dashboard-card"><span class="muted">Catalogues</span><strong>{{ $catalogTotal }}</strong><small>Actifs : {{ $activeCatalogTotal }}</small></article>
        <article class="card dashboard-card"><span class="muted">Produits</span><strong>{{ $productTotal }}</strong><small>Actifs : {{ $activeProductTotal }}</small></article>
        <article class="card dashboard-card"><span class="muted">Catégories</span><strong>{{ $categoryTotal }}</strong><small>Globales : {{ $globalCategoryTotal }} · Locales : {{ $localCategoryTotal }}</small></article>
    </section>
    <div class="dashboard-columns">
        <section class="card"><h2>Coopératives par statut</h2><table><tbody>@foreach($cooperativeStatuses as $status => $count)<tr><th>{{ ucfirst($status) }}</th><td>{{ $count }}</td></tr>@endforeach</tbody></table></section>
        <section class="card"><h2>Utilisateurs par statut</h2><table><tbody>@foreach($userStatuses as $status => $count)<tr><th>{{ ucfirst($status) }}</th><td>{{ $count }}</td></tr>@endforeach</tbody></table></section>
        <section class="card"><h2>Utilisateurs par rôle</h2><table><tbody>@foreach($usersByRole as $role => $count)<tr><th>{{ str($role)->replace('_', ' ')->title() }}</th><td>{{ $count }}</td></tr>@endforeach</tbody></table></section>
    </div>
    <section class="card">
        <h2>Activité récente</h2>
        @if($recentActivities->isEmpty())<p class="empty">Aucune activité enregistrée pour le moment.</p>
        @else
            <div class="table-wrap"><table><thead><tr><th>Événement</th><th>Acteur</th><th>Élément concerné</th><th>Date</th></tr></thead><tbody>
                @foreach($recentActivities as $activity)
                    <tr><td>{{ $activity->event_key }}</td><td>{{ $activity->actor_id ?? 'Système' }}</td><td>{{ $activity->subject_id ?? '—' }}</td><td>{{ $activity->occurred_at?->format('d/m/Y H:i') ?? '—' }}</td></tr>
                @endforeach
            </tbody></table></div>
        @endif
    </section>
@endsection

@push('styles')
<style>
.dashboard-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(175px,1fr));gap:14px}
.dashboard-card{display:flex;flex-direction:column;gap:8px;margin:0}
.dashboard-card strong{font-size:30px;color:#153d34}
.dashboard-card small{color:#65737e}
.dashboard-columns{display:grid;grid-template-columns:repeat(auto-fit,minmax(230px,1fr));gap:4px 14px;margin-top:18px}
.dashboard-columns .card{margin-bottom:10px}
</style>
@endpush
