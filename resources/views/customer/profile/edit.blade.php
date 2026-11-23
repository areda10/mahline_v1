@extends('layouts.customer')

@section('title', 'Mon profil')

@section('content')
    <section class="card">
        <h1>Mon profil</h1>
        <p class="muted">Gérez vos informations personnelles dans votre espace client.</p>

        @if (session('success'))
            <div class="alert alert-success" role="status">{{ session('success') }}</div>
        @endif

        @if ($errors->any())
            <div class="alert alert-error" role="alert">
                <strong>Veuillez vérifier les informations saisies.</strong>
                <ul>
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form method="POST" action="{{ route('customer.profile.update') }}">
            @csrf
            @method('PUT')

            <div class="form-field">
                <label for="first_name">Prénom</label>
                <input
                    id="first_name"
                    name="first_name"
                    type="text"
                    value="{{ old('first_name', $user->first_name) }}"
                    maxlength="100"
                    autocomplete="given-name"
                    required
                >
                @error('first_name')
                    <p class="field-error">{{ $message }}</p>
                @enderror
            </div>

            <div class="form-field">
                <label for="last_name">Nom</label>
                <input
                    id="last_name"
                    name="last_name"
                    type="text"
                    value="{{ old('last_name', $user->last_name) }}"
                    maxlength="100"
                    autocomplete="family-name"
                    required
                >
                @error('last_name')
                    <p class="field-error">{{ $message }}</p>
                @enderror
            </div>

            <div class="form-field">
                <label for="telephone">Téléphone</label>
                <input
                    id="telephone"
                    name="telephone"
                    type="tel"
                    value="{{ old('telephone', $user->telephone) }}"
                    maxlength="30"
                    autocomplete="tel"
                    required
                >
                @error('telephone')
                    <p class="field-error">{{ $message }}</p>
                @enderror
            </div>

            <div class="form-field">
                <label for="email">Adresse e-mail</label>
                <input id="email" type="email" value="{{ $user->email }}" readonly aria-describedby="email-help">
                <p id="email-help" class="muted">
                    Le changement d'adresse e-mail sera effectué par une procédure de vérification sécurisée.
                </p>
            </div>

            <h2>Adresse personnelle</h2>
            <p class="muted">Votre adresse personnelle est indépendante de celle d'une coopérative. Pays fixé à Maroc pour la V1.</p>
            <div class="form-field">
                <label for="number">Numéro</label>
                <input id="number" name="number" maxlength="50" value="{{ old('number', $address?->number) }}">
            </div>
            <div class="form-field">
                <label for="street">Rue</label>
                <input id="street" name="street" maxlength="255" required value="{{ old('street', $address?->street) }}">
                @error('street')<p class="field-error">{{ $message }}</p>@enderror
            </div>
            <div class="form-field">
                <label for="floor">Étage / Complément</label>
                <input id="floor" name="floor" maxlength="50" value="{{ old('floor', $address?->floor) }}">
            </div>
            <div class="form-field">
                <label for="city">Ville</label>
                <input id="city" name="city" maxlength="120" required value="{{ old('city', $address?->city) }}">
                @error('city')<p class="field-error">{{ $message }}</p>@enderror
            </div>
            <div class="form-field">
                <label for="region">Région</label>
                <select id="region" name="region" required>
                    <option value="">Sélectionner une région</option>
                    @foreach ($regions as $region)
                        <option value="{{ $region }}" @selected(old('region', $address?->region) === $region)>{{ $region }}</option>
                    @endforeach
                </select>
                @error('region')<p class="field-error">{{ $message }}</p>@enderror
            </div>
            <div class="form-field">
                <label for="postal_code">Code postal</label>
                <input id="postal_code" name="postal_code" maxlength="20" value="{{ old('postal_code', $address?->postal_code) }}">
            </div>
            <div class="form-field">
                <label for="country">Pays</label>
                <input id="country" type="text" value="Maroc" readonly>
            </div>

            <button type="submit">Enregistrer les modifications</button>
        </form>
        <section class="card">
            <h2>Mon carnet d’adresses</h2>
            <p class="muted">Vous pouvez enregistrer jusqu’à cinq adresses actives. Une seule peut être principale. La suppression est logique et libère une place.</p>
            @foreach($addresses as $savedAddress)
                <article class="address-card">
                    <p>
                        {{ $savedAddress->number ? $savedAddress->number.' ' : '' }}{{ $savedAddress->street }},
                        {{ $savedAddress->city }} — {{ $savedAddress->region ?? 'Région non précisée' }}
                        @if($savedAddress->postal_code) ({{ $savedAddress->postal_code }}) @endif
                    </p>
                    @if($savedAddress->is_primary)
                        <strong>Adresse principale</strong>
                    @else
                        <form method="POST" action="{{ route('customer.profile.addresses.primary', $savedAddress) }}">
                            @csrf
                            @method('PATCH')
                            <button type="submit">Définir comme principale</button>
                        </form>
                    @endif
                    <form method="POST" action="{{ route('customer.profile.addresses.destroy', $savedAddress) }}" onsubmit="return confirm('Supprimer cette adresse du carnet ?');">
                        @csrf
                        @method('DELETE')
                        <button type="submit">Supprimer</button>
                    </form>
                </article>
            @endforeach
            <h3>Ajouter une adresse</h3>
            <form method="POST" action="{{ route('customer.profile.addresses.store') }}">
                @csrf
                <div class="form-field"><label for="new_number">Numéro</label><input id="new_number" name="number" maxlength="50"></div>
                <div class="form-field"><label for="new_street">Rue</label><input id="new_street" name="street" maxlength="255" required></div>
                <div class="form-field"><label for="new_floor">Étage / Complément</label><input id="new_floor" name="floor" maxlength="50"></div>
                <div class="form-field"><label for="new_city">Ville</label><input id="new_city" name="city" maxlength="120" required></div>
                <div class="form-field">
                    <label for="new_region">Région</label>
                    <select id="new_region" name="region">
                        <option value="">Sélectionner une région</option>
                        @foreach($regions as $region)
                            <option value="{{ $region }}">{{ $region }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="form-field"><label for="new_postal_code">Code postal</label><input id="new_postal_code" name="postal_code" maxlength="20"></div>
                <button type="submit">Ajouter l’adresse</button>
            </form>
        </section>
    </section>

    <section class="card">
        <h2>Changer mon adresse e-mail</h2>
        <p class="muted">
            Votre adresse actuelle reste active jusqu’à la confirmation de la nouvelle adresse.
            Le lien de confirmation expire après 30 minutes. Après validation, toutes vos sessions
            et tous vos jetons API seront révoqués.
        </p>

        <form method="POST" action="{{ route('customer.profile.email.change') }}">
            @csrf

            <div class="form-field">
                <label for="new_email">Nouvelle adresse e-mail</label>
                <input
                    id="new_email"
                    name="new_email"
                    type="email"
                    value="{{ old('new_email') }}"
                    maxlength="255"
                    autocomplete="email"
                    required
                >
                @error('new_email')
                    <p class="field-error">{{ $message }}</p>
                @enderror
            </div>

            <div class="form-field">
                <label for="current_password">Mot de passe actuel</label>
                <input
                    id="current_password"
                    name="current_password"
                    type="password"
                    autocomplete="current-password"
                    required
                >
                @error('current_password')
                    <p class="field-error">{{ $message }}</p>
                @enderror
            </div>

            <button type="submit">Envoyer le lien de confirmation</button>
        </form>
    </section>

@endsection

@push('styles')
    <style>
        .address-card{padding:12px 0;border-bottom:1px solid #dce5df}.form-field{margin:18px 0}
        .form-field label{display:block;margin-bottom:7px;font-weight:600}
        .form-field input{width:100%;box-sizing:border-box;padding:12px;border:1px solid #cbd7d0;border-radius:7px;font:inherit}
        .form-field input[readonly]{background:#f4f7f5;color:#65736e}
        .field-error{color:#982d35;font-size:13px}
        button[type=submit]{padding:12px 18px;border:0;border-radius:7px;background:#176b57;color:#fff;font-weight:700;cursor:pointer}
    </style>
@endpush
