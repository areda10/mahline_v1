<div class="field">
    <label for="name">Nom du produit *</label>
    <input id="name" name="name" type="text" maxlength="180"
           value="{{ old('name', $product->name) }}" required>
    @error('name') <div class="error">{{ $message }}</div> @enderror
</div>

<div class="field">
    <label for="description">Description</label>
    <textarea id="description" name="description">{{ old('description', $product->description) }}</textarea>
    @error('description') <div class="error">{{ $message }}</div> @enderror
</div>

<div class="field">
    <label for="category_ids">Catégories *</label>
    <select id="category_ids" name="category_ids[]" multiple required>
        @php
            $selectedCategories = collect(old(
                'category_ids',
                $product->exists ? $product->categories->modelKeys() : []
            ))->map(fn ($id) => (string) $id)->all();
        @endphp

        @foreach($categories as $category)
            <option value="{{ $category->getKey() }}"
                @selected(in_array((string) $category->getKey(), $selectedCategories, true))>
                {{ $category->isGlobal() ? '[Globale] ' : '[Locale] ' }}{{ $category->name }}
            </option>
        @endforeach
    </select>
    <small class="muted">Utilise Ctrl (Windows) ou Cmd (Mac) pour sélectionner plusieurs catégories.</small>
    @error('category_ids') <div class="error">{{ $message }}</div> @enderror
    @error('category_ids.*') <div class="error">{{ $message }}</div> @enderror
</div>

<div class="field">
    <label for="catalog_ids">Catalogues</label>
    <select id="catalog_ids" name="catalog_ids[]" multiple>
        @php
            $selectedCatalogs = collect(old(
                'catalog_ids',
                $product->exists ? $product->catalogs->modelKeys() : []
            ))->map(fn ($id) => (string) $id)->all();
        @endphp

        @foreach($catalogs as $catalog)
            <option value="{{ $catalog->getKey() }}"
                @selected(in_array((string) $catalog->getKey(), $selectedCatalogs, true))>
                {{ $catalog->name }}
            </option>
        @endforeach
    </select>
    @error('catalog_ids') <div class="error">{{ $message }}</div> @enderror
    @error('catalog_ids.*') <div class="error">{{ $message }}</div> @enderror
</div>

<div class="field">
    <input type="hidden" name="is_active" value="0">
    <div class="check">
        <input id="is_active" type="checkbox" name="is_active" value="1"
               @checked(old('is_active', $product->exists ? $product->is_active : true))>
        <label for="is_active">Produit actif</label>
    </div>
    @error('is_active') <div class="error">{{ $message }}</div> @enderror
</div>

<div class="actions">
    <button class="button" type="submit">{{ $submitLabel }}</button>
    <a class="button button-secondary"
       href="{{ route('backoffice.products.index', ['cooperative' => $cooperative->getKey()]) }}">
        Annuler
    </a>
</div>