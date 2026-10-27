<div class="field">
    <label for="name">Nom du catalogue *</label>
    <input id="name" name="name" type="text" maxlength="150"
           value="{{ old('name', $catalog->name) }}" required>
    @error('name') <div class="error">{{ $message }}</div> @enderror
</div>

<div class="field">
    <label for="slug">Slug (facultatif)</label>
    <input id="slug" name="slug" type="text" maxlength="180"
           value="{{ old('slug', $catalog->slug) }}">
    @error('slug') <div class="error">{{ $message }}</div> @enderror
</div>

<div class="field">
    <label for="description">Description</label>
    <textarea id="description" name="description">{{ old('description', $catalog->description) }}</textarea>
    @error('description') <div class="error">{{ $message }}</div> @enderror
</div>

<div class="field">
    <input type="hidden" name="is_active" value="0">
    <div class="check">
        <input id="is_active" type="checkbox" name="is_active" value="1"
               @checked(old('is_active', $catalog->exists ? $catalog->is_active : true))>
        <label for="is_active">Catalogue actif</label>
    </div>
    @error('is_active') <div class="error">{{ $message }}</div> @enderror
</div>

<div class="actions">
    <button class="button" type="submit">{{ $submitLabel }}</button>
    <a class="button button-secondary"
       href="{{ route('backoffice.catalogs.index', ['cooperative' => $cooperative->getKey()]) }}">
        Annuler
    </a>
</div>