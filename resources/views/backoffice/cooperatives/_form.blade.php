@php
    $isCooperativeAdmin = auth()->user()->hasRole('cooperative_admin');
    $isCreating = ! $cooperative->exists;
    $address = $cooperative->address;
@endphp
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
    @if($isCreating)
        <fieldset class="form-fieldset">
            <legend>Adresse de la coopérative</legend>
            <label>Numéro <input name="number" maxlength="50" value="{{ old('number') }}"></label>
            <label>Rue / adresse <input name="street" required maxlength="255" value="{{ old('street') }}"></label>
            <label>Appartement / étage <input name="floor" maxlength="50" value="{{ old('floor') }}"></label>
            <label>Ville <input name="city" required maxlength="120" value="{{ old('city') }}"></label>
            <label>Région <input name="region" maxlength="120" value="{{ old('region') }}"></label>
            <label>Code postal <input name="postal_code" maxlength="20" value="{{ old('postal_code') }}"></label>
            <p>Pays : Maroc</p>
        </fieldset>
    @else
        <label>
            Adresse
            <select name="address_id" required>
                <option value="">Sélectionner une adresse</option>
                @foreach($addresses as $availableAddress)
                    <option value="{{ $availableAddress->getKey() }}" @selected((string) old('address_id', $cooperative->address_id) === (string) $availableAddress->getKey())>
                        {{ $availableAddress->address_line_1 }}
                        @if($availableAddress->address_line_2), {{ $availableAddress->address_line_2 }}@endif
                        — {{ $availableAddress->city }}
                        @if($availableAddress->country), {{ $availableAddress->country->name }}@endif
                    </option>
                @endforeach
            </select>
        </label>
    @endif
</div>
@foreach($errors->all() as $error)<p class="error">{{ $error }}</p>@endforeach
<div class="actions"><button class="button" type="submit">{{ $submitLabel }}</button><a class="button button-secondary" href="{{ $cooperative->exists ? route('backoffice.cooperatives.show', $cooperative) : route('backoffice.cooperatives.index') }}">Annuler</a></div>
