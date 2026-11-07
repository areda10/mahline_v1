@extends('layouts.admin')

@section('title', 'Sécurité — Changer le mot de passe')

@section('content')
    <div class="page-header">
        <div>
            <h1>Sécurité du compte</h1>
            <p class="muted">Modifiez votre mot de passe pour protéger votre compte MAHLINE.</p>
        </div>
    </div>

    <section class="card" style="max-width:680px">
        <h2>Changer le mot de passe</h2>

        @if ($errors->any())
            <div class="alert alert-error" role="alert">
                <ul>
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form method="POST" action="{{ route('password.force-change.update') }}">
            @csrf
            @method('PUT')

            @if(!auth()->user()->must_change_password)
                <div class="field">
                    <label for="current_password">Mot de passe actuel</label>
                    <input id="current_password" name="current_password" type="password" required autocomplete="current-password">
                </div>
            @else
                <p class="alert alert-error">Vous utilisez un mot de passe provisoire. Vous devez le remplacer avant de poursuivre.</p>
            @endif

            <div class="field">
                <label for="password">Nouveau mot de passe</label>
                <input id="password" name="password" type="password" required minlength="12" maxlength="128" autocomplete="new-password">
            </div>

            <div class="field">
                <label for="password_confirmation">Confirmer le nouveau mot de passe</label>
                <input id="password_confirmation" name="password_confirmation" type="password" required minlength="12" maxlength="128" autocomplete="new-password">
            </div>

            <p class="muted">Le mot de passe doit contenir au moins 12 caractères, une majuscule, une minuscule et un chiffre, sans espace.</p>

            <button class="button" type="submit">Enregistrer le nouveau mot de passe</button>
        </form>

        @if(auth()->user()->must_change_password)
            <form method="POST" action="{{ route('authentication.logout') }}" style="margin-top:16px">
                @csrf
                <button class="button button-secondary" type="submit">Se déconnecter</button>
            </form>
        @endif
    </section>
@endsection
