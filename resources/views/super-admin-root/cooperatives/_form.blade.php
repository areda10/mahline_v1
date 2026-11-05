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
    <label>
        Adresse
        <select name="address_id" required>
            <option value="">Sélectionner une adresse</option>
            @foreach($addresses as $address)

                <option value="{{ $address->getKey() }}" @selected((string) old('address_id', $cooperative->address_id) === (string) $address->getKey())>
                    {{ $address->address_line_1 }}
                    @if($address->address_line_2), {{ $address->address_line_2 }}@endif
                    — {{ $address->city }}
                    @if($address->country), {{ $address->country->name }}@endif
                </option>
            @endforeach
        </select>
    </label>
</div>
@foreach($errors->all() as $error)<p class="error">{{ $error }}</p>@endforeach
<div class="actions"><button class="button" type="submit">{{ $submitLabel }}</button><a class="button button-secondary" href="{{ $cooperative->exists ? route('root.backoffice.cooperatives.show', $cooperative) : route('root.backoffice.cooperatives.index') }}">Annuler</a></div>
