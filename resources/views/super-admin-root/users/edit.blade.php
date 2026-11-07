@extends('layouts.root')

@section('title', 'Modifier un utilisateur')

@section('content')
<div class="page-header">
    <div>
        <h1>Modifier un utilisateur</h1>
        <p class="muted">{{ $user->display_name }} — les changements de rôle révoquent les anciennes sessions et les anciens jetons API.</p>
    </div>
    <a class="button button-secondary" href="{{ route('root.backoffice.dashboard') }}">Retour au tableau de bord</a>
</div>

<section class="card">
    <form method="POST" action="{{ route('root.backoffice.users.update', $user) }}">
        @csrf
        @method('PUT')

        <div class="form-grid">
            <label>Prénom
                <input name="first_name" required maxlength="255" value="{{ old('first_name', $user->first_name) }}">
            </label>
            <label>Nom
                <input name="last_name" required maxlength="255" value="{{ old('last_name', $user->last_name) }}">
            </label>
            <label>Adresse e-mail
                <input type="email" name="email" required maxlength="255" value="{{ old('email', $user->email) }}">
            </label>
            <label>Téléphone
                <input name="telephone" required maxlength="255" value="{{ old('telephone', $user->telephone) }}">
            </label>
            <label>Rôle
                <select id="role_slug" name="role_slug" required>
                    @foreach($roles as $role)
                        <option value="{{ $role->slug }}" @selected(old('role_slug', $user->roles->first()?->slug) === $role->slug)>{{ $role->name }}</option>
                    @endforeach
                </select>
            </label>
            <label id="cooperative-field">Coopérative
                <select id="cooperative_id" name="cooperative_id">
                    <option value="">Sélectionner une coopérative</option>
                    @foreach($cooperatives as $cooperative)
                        <option value="{{ $cooperative->id }}" @selected((string) old('cooperative_id', $user->cooperative_id) === (string) $cooperative->id)>{{ $cooperative->name }}</option>
                    @endforeach
                </select>
            </label>
        </div>

        @foreach($errors->all() as $error)<p class="error">{{ $error }}</p>@endforeach
        <div class="actions">
            <button class="button" type="submit">Enregistrer les modifications</button>
            <a class="button button-secondary" href="{{ route('root.backoffice.users.show', $user) }}">Annuler</a>
        </div>
    </form>
</section>

<script>
(() => {
    const role = document.getElementById('role_slug');
    const field = document.getElementById('cooperative-field');
    const cooperative = document.getElementById('cooperative_id');
    const refresh = () => {
        const required = role.value !== 'super_admin';
        field.hidden = !required;
        cooperative.required = required;
        if (!required) cooperative.value = '';
    };
    role.addEventListener('change', refresh);
    refresh();
})();
</script>
@endsection
