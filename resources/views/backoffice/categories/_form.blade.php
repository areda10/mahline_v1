<div class="field">
    <label for="name">Nom de la catégorie *</label>
    <input id="name" name="name" type="text" maxlength="150"
           value="{{ old('name', $category->name) }}" required>
    @error('name') <div class="error">{{ $message }}</div> @enderror
</div>

<div class="field">
    <label for="slug">Slug (facultatif)</label>
    <input id="slug" name="slug" type="text" maxlength="180"
           value="{{ old('slug', $category->slug) }}">
    @error('slug') <div class="error">{{ $message }}</div> @enderror
</div>

<div class="field">
    <label for="description">Description</label>
    <textarea id="description" name="description">{{ old('description', $category->description) }}</textarea>
    @error('description') <div class="error">{{ $message }}</div> @enderror
</div>

<div class="field">
    <label for="parent_id">Catégorie parente</label>
    <select id="parent_id" name="parent_id">
        <option value="">Aucune (catégorie racine)</option>
        @foreach($categories as $parent)
            <option value="{{ $parent->getKey() }}"
                @selected((string) old('parent_id', $category->parent_id) === (string) $parent->getKey())>
                {{ $parent->isGlobal() ? '[Globale] ' : '[Locale] ' }}{{ $parent->name }}
            </option>
        @endforeach
    </select>
    @error('parent_id') <div class="error">{{ $message }}</div> @enderror
</div>

<div class="field">
    <label for="sort_order">Ordre d'affichage</label>
    <input id="sort_order" name="sort_order" type="number" min="0"
           value="{{ old('sort_order', $category->sort_order ?? 0) }}">
    @error('sort_order') <div class="error">{{ $message }}</div> @enderror
</div>

<div class="field">
    <input type="hidden" name="is_active" value="0">
    <div class="check">
        <input id="is_active" type="checkbox" name="is_active" value="1"
               @checked(old('is_active', $category->exists ? $category->is_active : true))>
        <label for="is_active">Catégorie active</label>
    </div>
    @error('is_active') <div class="error">{{ $message }}</div> @enderror
</div>

<div class="actions">
    <button class="button" type="submit">{{ $submitLabel }}</button>
    <a class="button button-secondary"
       href="{{ route('backoffice.categories.index', ['cooperative' => $cooperative->getKey()]) }}">
        Annuler
    </a>
</div>