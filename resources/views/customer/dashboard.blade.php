@extends('layouts.customer')

@section('title', 'Mon espace client')

@section('content')
    <section class="card">
        <h1>Bienvenue, {{ $user->display_name ?: $user->first_name }}</h1>
        <p class="muted">Votre espace client MAHLINE est actif.</p>
    </section>

    <section class="card">
        <h2>Mon profil</h2>
        <p><strong>Nom :</strong> {{ $user->display_name ?: trim($user->first_name.' '.$user->last_name) }}</p>
        <p><strong>E-mail :</strong> {{ $user->email }}</p>
        <p><strong>Téléphone :</strong> {{ $user->telephone }}</p>
        <p class="muted">La gestion complète du profil, de la photo et du carnet d'adresses sera ajoutée dans les prochaines étapes.</p>
    </section>
@endsection
