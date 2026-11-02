@php($isCooperativeAdmin = auth()->user()->hasRole('cooperative_admin'))
<div class="form-grid">
    @unless($isCooperativeAdmin)
        <label>Nom <input name="name" required maxlength="180" value="{{ old('name', $cooperative->name) }}"></label>
        <label>RIB <input name="rib" maxlength="34" value="{{ old('rib', $cooperative->rib) }}"></label>
        <label>Identifiant fiscal <input name="tax_id" value="{{ old('tax_id', $cooperative->tax_id) }}"></label>
        <label>RC <input name="rc" value="{{ old('rc', $cooperative->rc) }}"></label>
        <label>ICE <input name="ice" value="{{ old('ice', $cooperative->ice) }}"></label>
    @endunless
    <label>Téléphone <input name="phone" maxlength="30" value="{{ old('phone', $cooperative->phone) }}"></label>
    <label>Email <input type="email" name="email" value="{{ old('email', $cooperative->email) }}"></label>
    <label>Identifiant d'adresse <input name="address_id" maxlength="26" value="{{ old('address_id', $cooperative->address_id) }}"></label>
</div>
@foreach($errors->all() as $error)<p class="error">{{ $error }}</p>@endforeach
<div class="actions"><button class="button" type="submit">{{ $submitLabel }}</button><a class="button button-secondary" href="{{ $cooperative->exists ? route('backoffice.cooperatives.show', $cooperative) : route('backoffice.cooperatives.index') }}">Annuler</a></div>
