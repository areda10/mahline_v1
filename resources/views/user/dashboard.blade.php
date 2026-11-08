@extends('layouts.admin')

@section('title', 'Mon tableau de bord')

@section('content')
    <div class="page-header">
        <div>
            <h1>Bienvenue, {{ $user->display_name ?: $user->first_name }}</h1>
            <p class="muted">Votre espace personnel MAHLINE</p>
        </div>
    </div>

    <section class="dashboard-grid" aria-label="Informations du compte">
        <article class="card dashboard-card">
            <span class="muted">Mon compte</span>
            <strong>{{ $user->display_name ?: trim($user->first_name.' '.$user->last_name) }}</strong>
            <small>{{ $user->email }}</small>
        </article>

        <article class="card dashboard-card">
            <span class="muted">Statut du compte</span>
            <strong>{{ $user->status->value }}</strong>
            <small>Compte MAHLINE</small>
        </article>

        <article class="card dashboard-card">
            <span class="muted">Ma coopérative</span>
            <strong>{{ $user->cooperative?->name ?? 'Non affecté' }}</strong>
            <small>Coopérative associée à votre compte</small>
        </article>
    </section>

    <section class="card">
        <h2>Mon espace</h2>
        <p>Bienvenue dans votre espace personnel. Les fonctionnalités disponibles seront affichées ici selon les autorisations associées à votre compte.</p>
    </section>
@endsection

@push('styles')
<style>
.dashboard-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:14px}
.dashboard-card{display:flex;flex-direction:column;gap:8px;margin:0;min-width:0}
.dashboard-card strong{font-size:20px;color:#153d34;overflow-wrap:anywhere}
.dashboard-card small{color:#65737e;overflow-wrap:anywhere}
</style>
@endpush
