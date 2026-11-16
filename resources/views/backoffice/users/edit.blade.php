@extends('layouts.admin')

@section('title', 'Modifier la fiche — '.$user->display_name)

@section('content')
<div class="page-header">
    <div>
        <h1>Modifier la fiche utilisateur</h1>
        <p class="muted">Seules les coordonnées autorisées peuvent être modifiées. Le rôle et la coopérative restent inchangés.</p>
    </div>
    <a class="button button-secondary" href="{{ route('backoffice.users.show', $user) }}">Retour à la fiche</a>
</div>

<section class="card" aria-labelledby="user-edit-heading">
    <h2 id="user-edit-heading">Identité et coordonnées</h2>

    <form method="POST" action="{{ route('backoffice.users.update', $user) }}">
        @csrf
        @method('PUT')

        <div class="form-grid">
            <label for="first_name">Prénom
                <input id="first_name" name="first_name" required maxlength="255" value="{{ old('first_name', $user->first_name) }}">
            </label>

            <label for="last_name">Nom
                <input id="last_name" name="last_name" required maxlength="255" value="{{ old('last_name', $user->last_name) }}">
            </label>

            <label for="email">Adresse email
                <input id="email" type="email" name="email" required maxlength="255" value="{{ old('email', $user->email) }}">
            </label>

            <label for="telephone">Téléphone
                <input id="telephone" name="telephone" required maxlength="255" value="{{ old('telephone', $user->telephone) }}">
            </label>
        </div>

        <div class="actions">
            <button class="button" type="submit">Enregistrer les modifications</button>
            <a class="button button-secondary" href="{{ route('backoffice.users.show', $user) }}">Annuler</a>
        </div>
    </form>
</section>
@endsection
