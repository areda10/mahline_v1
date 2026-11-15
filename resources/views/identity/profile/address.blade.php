@extends($layout)

@section('title', 'Mon adresse personnelle')

@section('content')
<section class="card">
    <h1>Mon adresse personnelle</h1>
    <p class="muted">Votre adresse est indépendante de celle de votre coopérative. Le pays est fixé à Maroc pour la version V1.</p>

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

    <form method="POST" action="{{ route($user->hasRole('super_admin_root') ? 'root.backoffice.profile.address.update' : 'profile.address.update') }}">
        @csrf
        @method('PUT')

        <div class="form-grid">
            <label>Numéro
                <input name="number" maxlength="50" value="{{ old('number', $address?->number) }}">
            </label>
            <label>Rue
                <input name="street" maxlength="255" required value="{{ old('street', $address?->street) }}">
            </label>
            <label>Étage / Complément
                <input name="floor" maxlength="50" value="{{ old('floor', $address?->floor) }}">
            </label>
            <label>Ville
                <input name="city" maxlength="120" required value="{{ old('city', $address?->city) }}">
            </label>
            <label>Région
                <select name="region" required>
                    <option value="">Sélectionner une région</option>
                    @foreach ($regions as $region)
                        <option value="{{ $region }}" @selected(old('region', $address?->region) === $region)>{{ $region }}</option>
                    @endforeach
                </select>
            </label>
            <label>Code postal
                <input name="postal_code" maxlength="20" value="{{ old('postal_code', $address?->postal_code) }}">
            </label>
            <label>Pays
                <input type="text" value="Maroc" readonly>
            </label>
        </div>

        <div class="actions">
            <button class="button" type="submit">Enregistrer mon adresse</button>
        </div>
    </form>
</section>
@endsection
