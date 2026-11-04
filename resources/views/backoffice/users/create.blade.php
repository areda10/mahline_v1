@extends('layouts.admin')

@section('title', 'Créer un utilisateur')

@section('content')
<div class="page-header">
    <div>
        <h1>Créer un utilisateur</h1>
        <p class="muted">Le compte sera créé en attente et une invitation sera envoyée à son adresse e-mail.</p>
    </div>

    <div class="actions">
        <a class="button button-secondary" href="{{ route('backoffice.users.index') }}">Retour à la liste</a>
    </div>
</div>

@if(session('error'))
    <div class="alert alert-danger" role="alert">{{ session('error') }}</div>
@endif

<section class="card" aria-labelledby="user-create-heading">
    <h2 id="user-create-heading">Informations du compte</h2>

    <form method="POST" action="{{ route('backoffice.users.store') }}">
        @csrf

        <div class="field">
            <label for="first_name">Prénom</label>
            <input
                id="first_name"
                name="first_name"
                type="text"
                value="{{ old('first_name') }}"
                maxlength="255"
                autocomplete="given-name"
                required
            >
            @error('first_name')
                <div class="field-error">{{ $message }}</div>
            @enderror
        </div>

        <div class="field">
            <label for="last_name">Nom</label>
            <input
                id="last_name"
                name="last_name"
                type="text"
                value="{{ old('last_name') }}"
                maxlength="255"
                autocomplete="family-name"
                required
            >
            @error('last_name')
                <div class="field-error">{{ $message }}</div>
            @enderror
        </div>

        <div class="field">
            <label for="email">Adresse e-mail</label>
            <input
                id="email"
                name="email"
                type="email"
                value="{{ old('email') }}"
                maxlength="255"
                autocomplete="email"
                required
            >
            @error('email')
                <div class="field-error">{{ $message }}</div>
            @enderror
        </div>

        <div class="field">
            <label for="telephone">Téléphone</label>
            <input
                id="telephone"
                name="telephone"
                type="tel"
                value="{{ old('telephone') }}"
                maxlength="255"
                autocomplete="tel"
                required
            >
            @error('telephone')
                <div class="field-error">{{ $message }}</div>
            @enderror
        </div>

        <div class="field">
            <label for="role_slug">Rôle</label>
            <select id="role_slug" name="role_slug" required>
                <option value="">Sélectionner un rôle</option>
                @foreach($roles as $role)
                    <option
                        value="{{ $role->slug }}"
                        @selected(old('role_slug') === $role->slug)
                    >
                        {{ $role->name }}
                    </option>
                @endforeach
            </select>
            @error('role_slug')
                <div class="field-error">{{ $message }}</div>
            @enderror
        </div>

        <div class="field" id="cooperative-field">
            <label for="cooperative_id">Coopérative</label>
            <select id="cooperative_id" name="cooperative_id">
                <option value="">Sélectionner une coopérative</option>
                @foreach($cooperatives as $cooperative)
                    <option
                        value="{{ $cooperative->id }}"
                        @selected(old('cooperative_id') === $cooperative->id)
                    >
                        {{ $cooperative->name }}
                    </option>
                @endforeach
            </select>
            @error('cooperative_id')
                <div class="field-error">{{ $message }}</div>
            @enderror
            @if($cooperatives->isEmpty())
                <p class="muted">Aucune coopérative n'est disponible dans votre périmètre d'affectation.</p>
            @endif
        </div>

        <p class="muted">
            Le compte sera créé avec le statut « En attente ». L'utilisateur recevra un lien valable 60 minutes pour définir son mot de passe.
            Aucun mot de passe temporaire ne sera communiqué à l'administrateur.
        </p>

        <div class="actions">
            <button class="button" type="submit">Créer le compte et envoyer l'invitation</button>
            <a class="button button-secondary" href="{{ route('backoffice.users.index') }}">Annuler</a>
        </div>
    </form>
</section>

<script>
    (() => {
        const roleSelect = document.getElementById('role_slug');
        const cooperativeField = document.getElementById('cooperative-field');
        const cooperativeSelect = document.getElementById('cooperative_id');

        if (!roleSelect || !cooperativeField || !cooperativeSelect) {
            return;
        }

        const updateCooperativeField = () => {
            const requiresCooperative = ['cooperative_admin', 'user'].includes(roleSelect.value);

            cooperativeField.hidden = !requiresCooperative;
            cooperativeSelect.required = requiresCooperative;

            if (!requiresCooperative) {
                cooperativeSelect.value = '';
            }
        };

        roleSelect.addEventListener('change', updateCooperativeField);
        updateCooperativeField();
    })();
</script>
@endsection
