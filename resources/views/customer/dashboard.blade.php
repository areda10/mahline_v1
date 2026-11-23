@extends('layouts.customer')

@section('title', 'Mon espace client')

@section('content')
    <section class="card">
        <h1>Bienvenue, {{ $user->display_name ?: $user->first_name }}</h1>
        <p class="muted">Votre espace client MAHLINE est actif.</p>
    </section>

    <section class="card">
        <h2>Mes achats</h2>
        <p class="muted">Accédez à la boutique, retrouvez votre panier et suivez vos commandes.</p>
        <div class="actions">
            <a class="button" href="{{ route('storefront.products.index') }}">Parcourir la boutique</a>
            <a class="button button-secondary" href="{{ route('sales.cart.page') }}">Voir mon panier</a>
            <a class="button button-secondary" href="{{ route('customer.orders.index') }}">Suivre mes commandes</a>
        </div>
    </section>

    <section class="card">
        <h2>Mon compte et ma sécurité</h2>
        <p class="muted">Gérez vos informations, vos adresses et les appareils autorisés.</p>
        <div class="actions">
            <a class="button button-secondary" href="{{ route('customer.profile.edit') }}">Profil et carnet d’adresses</a>
            <a class="button button-secondary" href="{{ route('customer.security.password.edit') }}">Mot de passe</a>
            <a class="button button-secondary" href="{{ route('customer.security.devices.index') }}">Mes appareils</a>
            <a class="button button-secondary" href="{{ route('notifications.index') }}">Mes notifications</a>
        </div>
    </section>

    <section class="card">
        <div class="section-heading">
            <h2>Mon profil</h2>
            <a href="{{ route('customer.profile.edit') }}">Modifier mon profil</a>
        </div>
        <p><strong>Nom :</strong> {{ $user->display_name ?: trim($user->first_name.' '.$user->last_name) }}</p>
        <p><strong>E-mail :</strong> {{ $user->email }}</p>
        <p><strong>Téléphone :</strong> {{ $user->telephone }}</p>
        <p class="muted">Vous pouvez modifier vos informations personnelles depuis votre espace client.</p>
    </section>
@endsection
