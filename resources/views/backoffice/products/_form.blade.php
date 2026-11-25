@php
    $selectedType = old('product_type', $product->exists ? ($product->product_type ?? 'simple') : 'simple');
    $selectedCategories = collect(old('category_ids', $product->exists ? $product->categories->modelKeys() : []))->map(fn ($id) => (string) $id)->all();
    $selectedCatalogs = collect(old('catalog_ids', $product->exists ? $product->catalogs->modelKeys() : []))->map(fn ($id) => (string) $id)->all();
    $variantRows = old('variants', [['name' => '', 'price' => '', 'unit_id' => '', 'sku' => '']]);
@endphp

@if(!$product->exists)
<div class="field">
    <label>Type de produit *</label>
    <div class="check">
        <input id="type_simple" type="radio" name="product_type" value="simple" @checked($selectedType === 'simple')>
        <label for="type_simple">Produit simple</label>
    </div>
    <div class="check">
        <input id="type_variant" type="radio" name="product_type" value="variant" @checked($selectedType === 'variant')>
        <label for="type_variant">Produit avec variantes</label>
    </div>
    @error('product_type') <div class="error">{{ $message }}</div> @enderror
    <small class="muted">Le type choisi sera définitif après la création.</small>
</div>
@else
<div class="field">
    <label>Type de produit</label>
    <p class="muted">{{ ($product->product_type ?? 'simple') === 'variant' ? 'Produit avec variantes' : 'Produit simple' }} — le type ne peut pas être modifié.</p>
</div>
@endif

<div class="field">
    <label for="name">Nom du produit *</label>
    <input id="name" name="name" type="text" maxlength="180" value="{{ old('name', $product->name) }}" required>
    @error('name') <div class="error">{{ $message }}</div> @enderror
</div>

<div id="simple-fields" @if($selectedType === 'variant') hidden @endif>
    @if(!$product->exists || $selectedType === 'simple')
    <div class="field">
        <label for="price">Prix de vente (MAD) *</label>
        <input id="price" name="price" type="number" min="0.01" step="0.01" value="{{ old('price', $product->price) }}">
        @error('price') <div class="error">{{ $message }}</div> @enderror
    </div>
    <div class="field">
        <label for="unit_id">Unité de stock *</label>
        <select id="unit_id" name="unit_id">
            <option value="">— Sélectionner une unité —</option>
            @foreach($productUnits as $unit)
                <option value="{{ $unit->getKey() }}" @selected((string) old('unit_id', $product->unit_id) === (string) $unit->getKey())>{{ $unit->label }}</option>
            @endforeach
        </select>
        @error('unit_id') <div class="error">{{ $message }}</div> @enderror
    </div>
    @endif
</div>

<div id="variant-fields" @if($selectedType !== 'variant') hidden @endif>
    <p class="muted">Le produit parent ne porte ni prix de vente ni unité de stock. Définissez ces informations sur chaque variante.</p>
    @if(!$product->exists)
    <div id="variant-rows">
        @foreach($variantRows as $index => $variant)
            <fieldset class="card variant-row" style="margin-bottom:12px">
                <legend>Variante <span class="variant-number">{{ $index + 1 }}</span></legend>
                <div class="field">
                    <label>Libellé *</label>
                    <input name="variants[{{ $index }}][name]" value="{{ $variant['name'] ?? '' }}" maxlength="180">
                </div>
                <div class="field">
                    <label>Prix (MAD) *</label>
                    <input type="number" min="0.01" step="0.01" name="variants[{{ $index }}][price]" value="{{ $variant['price'] ?? '' }}">
                </div>
                <div class="field">
                    <label>Unité de stock *</label>
                    <select name="variants[{{ $index }}][unit_id]">
                        <option value="">— Sélectionner une unité —</option>
                        @foreach($productUnits as $unit)
                            <option value="{{ $unit->getKey() }}" @selected((string) ($variant['unit_id'] ?? '') === (string) $unit->getKey())>{{ $unit->label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="field">
                    <label>SKU (facultatif)</label>
                    <input name="variants[{{ $index }}][sku]" maxlength="100" value="{{ $variant['sku'] ?? '' }}">
                </div>
                @if(!$product->exists)
                    <button type="button" class="button button-secondary remove-variant">Retirer cette variante</button>
                @endif
            </fieldset>
        @endforeach
    </div>
        <button type="button" class="button button-secondary" id="add-variant">Ajouter une variante</button>
    @else
        <p class="muted">Les variantes se créent et se modifient depuis la fiche du produit. Le type du produit ne peut pas être modifié.</p>
    @endif
    @error('variants') <div class="error">{{ $message }}</div> @enderror
    @foreach($errors->getMessages() as $key => $messages)
        @if(str_starts_with($key, 'variants.'))
            <div class="error">{{ implode(' ', $messages) }}</div>
        @endif
    @endforeach
</div>

<div class="field">
    <label for="description">Description</label>
    <textarea id="description" name="description">{{ old('description', $product->description) }}</textarea>
    @error('description') <div class="error">{{ $message }}</div> @enderror
</div>

<div class="field">
    <label for="category_ids">Catégories *</label>
    <select id="category_ids" name="category_ids[]" multiple>
        @foreach($categories as $category)
            <option value="{{ $category->getKey() }}" @selected(in_array((string) $category->getKey(), $selectedCategories, true))>{{ $category->isGlobal() ? '[Globale] ' : '[Locale] ' }}{{ $category->name }}</option>
        @endforeach
    </select>
    <small class="muted">Utilise Ctrl (Windows) ou Cmd (Mac) pour sélectionner plusieurs catégories.</small>
    @error('category_ids') <div class="error">{{ $message }}</div> @enderror
    @error('category_ids.*') <div class="error">{{ $message }}</div> @enderror
</div>

<div class="field">
    <label for="catalog_ids">Catalogues</label>
    <select id="catalog_ids" name="catalog_ids[]" multiple>
        @foreach($catalogs as $catalog)
            <option value="{{ $catalog->getKey() }}" @selected(in_array((string) $catalog->getKey(), $selectedCatalogs, true))>{{ $catalog->name }}</option>
        @endforeach
    </select>
    @error('catalog_ids') <div class="error">{{ $message }}</div> @enderror
    @error('catalog_ids.*') <div class="error">{{ $message }}</div> @enderror
</div>

<div class="field">
    <input type="hidden" name="is_active" value="0">
    <div class="check">
        <input id="is_active" type="checkbox" name="is_active" value="1" @checked(old('is_active', $product->exists ? $product->is_active : true))>
        <label for="is_active">Produit actif</label>
    </div>
    @error('is_active') <div class="error">{{ $message }}</div> @enderror
</div>

<div class="actions">
    <button class="button" type="submit">{{ $submitLabel }}</button>
    <a class="button button-secondary" href="{{ route('backoffice.products.index', ['cooperative' => $cooperative->getKey()]) }}">Annuler</a>
</div>

@if(!$product->exists)
<script>
document.addEventListener('DOMContentLoaded', () => {
    const simple = document.getElementById('simple-fields');
    const variants = document.getElementById('variant-fields');
    const rows = document.getElementById('variant-rows');
    const categories = document.getElementById('category_ids');
    const syncMode = () => {
        const isVariant = document.getElementById('type_variant').checked;
        simple.hidden = isVariant;
        variants.hidden = !isVariant;
        simple.querySelectorAll('input,select').forEach((el) => el.required = !isVariant && el.name !== '');
        variants.querySelectorAll('input:not([name$="[sku]"]),select').forEach((el) => el.required = isVariant);
        categories.required = isVariant;
    };
    document.querySelectorAll('input[name="product_type"]').forEach((el) => el.addEventListener('change', syncMode));
    let nextIndex = rows.querySelectorAll('.variant-row').length;
    document.getElementById('add-variant').addEventListener('click', () => {
        const template = rows.querySelector('.variant-row').cloneNode(true);
        template.querySelectorAll('input').forEach((el) => {
            el.value = '';
            el.name = el.name.replace(/variants\[\d+\]/, 'variants[' + nextIndex + ']');
        });
        template.querySelectorAll('select').forEach((el) => {
            el.selectedIndex = 0;
            el.name = el.name.replace(/variants\[\d+\]/, 'variants[' + nextIndex + ']');
        });
        template.querySelector('.variant-number').textContent = nextIndex + 1;
        rows.appendChild(template);
        nextIndex++;
    });
    rows.addEventListener('click', (event) => {
        if (event.target.classList.contains('remove-variant') && rows.querySelectorAll('.variant-row').length > 1) {
            event.target.closest('.variant-row').remove();
        }
    });
    syncMode();
});
</script>
@endif
