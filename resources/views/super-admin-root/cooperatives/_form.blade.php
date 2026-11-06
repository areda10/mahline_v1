@php($isCooperativeAdmin = auth()->user()->hasRole('cooperative_admin'))
<div class="form-grid">
    @unless($isCooperativeAdmin)
        <label>Nom <input name="name" required maxlength="255" value="{{ old('name', $cooperative->name) }}"></label>
        <label>RIB <input name="rib" required maxlength="255" value="{{ old('rib', $cooperative->rib) }}"></label>
        <label>Identifiant fiscal <input name="tax_id" maxlength="255" value="{{ old('tax_id', $cooperative->tax_id) }}"></label>
        <label>RC <input name="rc" maxlength="255" value="{{ old('rc', $cooperative->rc) }}"></label>
        <label>ICE <input name="ice" required maxlength="255" value="{{ old('ice', $cooperative->ice) }}"></label>
    @endunless
    <label>Téléphone <input name="phone" required maxlength="255" value="{{ old('phone', $cooperative->phone) }}"></label>
    <label>Email <input type="email" name="email" required maxlength="255" value="{{ old('email', $cooperative->email) }}"></label>
    @php($address = $cooperative->address)
    <label>Adresse <input name="address_line_1" required maxlength="255" value="{{ old('address_line_1', $address?->address_line_1) }}"></label>
    <label>Ville <input name="city" required maxlength="255" value="{{ old('city', $address?->city) }}"></label>
    <label>Pays
        <select name="country_id" required>
            <option value="">Sélectionner un pays</option>
            @foreach($countries as $country)
                <option value="{{ $country->getKey() }}" @selected((string) old('country_id', $address?->country_id ?? $country->getKey()) === (string) $country->getKey())>Maroc</option>
            @endforeach
        </select>
    </label>
    <label>Code postal <input name="postal_code" required maxlength="32" value="{{ old('postal_code', $address?->postal_code) }}"></label>
    <label>Région
        <select name="state" required>
            <option value="">Sélectionner une région</option>
            @foreach([
                'Tanger-Tétouan-Al Hoceïma',
                "L'Oriental",
                'Fès-Meknès',
                'Rabat-Salé-Kénitra',
                'Béni Mellal-Khénifra',
                'Casablanca-Settat',
                'Marrakech-Safi',
                'Drâa-Tafilalet',
                'Souss-Massa',
                'Guelmim-Oued Noun',
                'Laâyoune-Sakia El Hamra',
                'Dakhla-Oued Ed-Dahab',
            ] as $region)
                <option value="{{ $region }}" @selected(old('state', $address?->state) === $region)>{{ $region }}</option>
            @endforeach
        </select>
    </label>
</div>
@foreach($errors->all() as $error)<p class="error">{{ $error }}</p>@endforeach
<div class="actions"><button class="button" type="submit">{{ $submitLabel }}</button><a class="button button-secondary" href="{{ $cooperative->exists ? route('root.backoffice.cooperatives.show', $cooperative) : route('root.backoffice.cooperatives.index') }}">Annuler</a></div>
