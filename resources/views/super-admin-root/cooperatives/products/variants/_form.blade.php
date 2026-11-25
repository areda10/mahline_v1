<div class="field">
    <label for="name">Libellé de la variante *</label>
    <input id="name" name="name" type="text" maxlength="180" required value="{{ old('name', $variant->label) }}">
    @error('name') <div class="error">{{ $message }}</div> @enderror
</div>

<div class="field">
    <label for="price">Prix de vente (MAD) *</label>
    <input id="price" name="price" type="number" min="0.01" step="0.01" required value="{{ old('price', $variant->price) }}">
    @error('price') <div class="error">{{ $message }}</div> @enderror
</div>

<div class="field">
    <label for="unit_id">Unité de stock *</label>
    <select id="unit_id" name="unit_id" required>
        <option value="">— Sélectionner une unité —</option>
        @foreach($productUnits as $unit)
            <option value="{{ $unit->getKey() }}" @selected((string) old('unit_id', $variant->unit_id) === (string) $unit->getKey())>{{ $unit->label }}</option>
        @endforeach
    </select>
    @error('unit_id') <div class="error">{{ $message }}</div> @enderror
    <small class="muted">L’unité ne pourra plus être changée après l’initialisation du stock de cette variante.</small>
</div>

<div class="field">
    <label for="sku">SKU (facultatif)</label>
    <input id="sku" name="sku" type="text" maxlength="100" value="{{ old('sku', $variant->sku) }}">
    @error('sku') <div class="error">{{ $message }}</div> @enderror
</div>

<div class="field">
    <input type="hidden" name="is_active" value="0">
    <div class="check">
        <input id="is_active" type="checkbox" name="is_active" value="1" @checked(old('is_active', $variant->exists ? $variant->is_active : true))>
        <label for="is_active">Variante active</label>
    </div>
    @error('is_active') <div class="error">{{ $message }}</div> @enderror
</div>

<div class="actions">
    <button class="button" type="submit">{{ $submitLabel }}</button>
    <a class="button button-secondary" href="{{ route('root.backoffice.cooperatives.products.show', ['cooperative' => $cooperative->getKey(), 'product' => $product->getKey()]) }}">Annuler</a>
</div>
