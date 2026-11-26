@extends('layouts.admin')
@section('title', 'Mon profil')
@section('content')
<div class="page-header"><div><h1>Mon profil</h1><p class="muted">Gérez votre photo, vos informations personnelles et votre adresse personnelle.</p></div></div>
<section class="card profile-photo-card">
    <h2>Photo de profil</h2>
    <div class="profile-photo-layout">
        @if ($user->profileImage)
            <img class="profile-photo-preview" src="{{ route('media.private', $user->profileImage) }}?variant=thumbnail" alt="Votre photo de profil" width="120" height="120">
        @else
            <div class="profile-photo-placeholder" aria-label="Aucune photo de profil">{{ mb_strtoupper(mb_substr($user->first_name ?: 'U', 0, 1)) }}</div>
        @endif
        <div>
            <form method="POST" action="{{ route('backoffice.profile.photo.store') }}" enctype="multipart/form-data">
                @csrf
                <div class="field">
                    <label for="profile-photo-file">Choisir une photo (JPG, PNG ou WebP — 5 Mo maximum)</label>
                    <input id="profile-photo-file" type="file" name="file" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp" required>
                    @error('file') <p class="error">{{ $message }}</p> @enderror
                </div>
                <button class="button" type="submit">{{ $user->profileImage ? 'Remplacer la photo' : 'Ajouter une photo' }}</button>
            </form>
            @if ($user->profileImage)
                <form method="POST" action="{{ route('backoffice.profile.photo.destroy') }}" class="profile-photo-delete">
                    @csrf
                    @method('DELETE')
                    <button class="button button-danger" type="submit">Supprimer la photo</button>
                </form>
            @endif
        </div>
    </div>
</section>
<section class="card">
    <h2>Informations personnelles</h2>
    <form method="POST" action="{{ route('backoffice.profile.update') }}">
        @csrf
        @method('PUT')
        <div class="form-grid">
            <label for="first_name">Prénom
                <input id="first_name" name="first_name" type="text" maxlength="100" autocomplete="given-name" required value="{{ old('first_name', $user->first_name) }}">
                @error('first_name') <span class="error">{{ $message }}</span> @enderror
            </label>
            <label for="last_name">Nom
                <input id="last_name" name="last_name" type="text" maxlength="100" autocomplete="family-name" required value="{{ old('last_name', $user->last_name) }}">
                @error('last_name') <span class="error">{{ $message }}</span> @enderror
            </label>
            <label for="telephone">Téléphone
                <input id="telephone" name="telephone" type="tel" maxlength="30" autocomplete="tel" required value="{{ old('telephone', $user->telephone) }}">
                @error('telephone') <span class="error">{{ $message }}</span> @enderror
            </label>
            <label for="email">Adresse e-mail
                <input id="email" type="email" value="{{ $user->email }}" readonly>
                <small class="muted">Le changement d’e-mail suit une procédure de vérification distincte.</small>
            </label>
        </div>
        <div class="actions"><button class="button" type="submit">Enregistrer mes informations</button></div>
    </form>
</section>
<section class="card">
    <h2>Adresse personnelle</h2>
    <p class="muted">Cette adresse est personnelle et indépendante de l’adresse de votre coopérative. Le pays est fixé à Maroc pour la V1.</p>
    <form method="POST" action="{{ route('backoffice.profile.address.update') }}">
        @csrf
        @method('PUT')
        <div class="form-grid">
            <label for="number">Numéro
                <input id="number" name="number" maxlength="50" value="{{ old('number', $address?->number) }}">
                @error('number') <span class="error">{{ $message }}</span> @enderror
            </label>
            <label for="street">Rue
                <input id="street" name="street" maxlength="255" required value="{{ old('street', $address?->street) }}">
                @error('street') <span class="error">{{ $message }}</span> @enderror
            </label>
            <label for="floor">Étage / Complément
                <input id="floor" name="floor" maxlength="50" value="{{ old('floor', $address?->floor) }}">
                @error('floor') <span class="error">{{ $message }}</span> @enderror
            </label>
            <label for="city">Ville
                <input id="city" name="city" maxlength="120" required value="{{ old('city', $address?->city) }}">
                @error('city') <span class="error">{{ $message }}</span> @enderror
            </label>
            <label for="region">Région
                <select id="region" name="region" required>
                    <option value="">Sélectionner une région</option>
                    @foreach ($regions as $region)
                        <option value="{{ $region }}" @selected(old('region', $address?->region) === $region)>{{ $region }}</option>
                    @endforeach
                </select>
                @error('region') <span class="error">{{ $message }}</span> @enderror
            </label>
            <label for="postal_code">Code postal
                <input id="postal_code" name="postal_code" maxlength="20" value="{{ old('postal_code', $address?->postal_code) }}">
                @error('postal_code') <span class="error">{{ $message }}</span> @enderror
            </label>
            <label for="country">Pays<input id="country" type="text" value="Maroc" readonly></label>
        </div>
        <div class="actions"><button class="button" type="submit">Enregistrer mon adresse</button></div>
    </form>
</section>
@endsection
@push('styles')
<style>
.profile-photo-layout{display:flex;align-items:flex-start;gap:22px;flex-wrap:wrap}
.profile-photo-preview,.profile-photo-placeholder{width:120px;height:120px;border-radius:50%;object-fit:cover;border:2px solid #dce8e2}
.profile-photo-placeholder{display:flex;align-items:center;justify-content:center;background:#e8efec;color:#153d34;font-size:42px;font-weight:700}
.profile-photo-delete{margin-top:12px}
</style>
@endpush
