@extends('layouts.admin')

@section('title', $catalog->name)

@section('content')
<div class="page-header">
    <div>
        <h1>{{ $catalog->name }}</h1>
        <p class="muted">{{ $catalog->description }}</p>
    </div>

    <div class="actions">
        <a class="button button-secondary"
           href="{{ route('backoffice.catalogs.index', ['cooperative' => $cooperative->getKey()]) }}">
            Retour
        </a>

        @can('update', $catalog)
            <a class="button"
               href="{{ route('backoffice.catalogs.edit', ['cooperative' => $cooperative->getKey(), 'catalog' => $catalog->getKey()]) }}">
                Modifier
            </a>
        @endcan
    </div>
</div>

<div class="card">
    <p><strong>Slug :</strong> {{ $catalog->slug }}</p>
    <p><strong>État :</strong> {{ $catalog->is_active ? 'Actif' : 'Inactif' }}</p>
</div>

<div class="card">
    <div class="page-header">
        <h2>Produits associés</h2>
    </div>

    @can('update', $catalog)
        @if($catalog->is_active)
            <form method="POST"
                  action="{{ route('backoffice.catalogs.products.store', ['cooperative' => $cooperative->getKey(), 'catalog' => $catalog->getKey()]) }}">
                @csrf
                <div class="field">
                    <label for="product_id">Identifiant du produit à ajouter</label>
                    <input id="product_id" name="product_id" type="text"
                           maxlength="26" value="{{ old('product_id') }}" required>
                    @error('product_id') <div class="error">{{ $message }}</div> @enderror
                </div>
                <button class="button" type="submit">Ajouter le produit</button>
            </form>
        @endif
    @endcan

    <div class="table-wrap" style="margin-top:20px">
        <table>
            <thead>
                <tr>
                    <th>Produit</th>
                    <th>État</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                @forelse($catalog->products as $product)
                    <tr>
                        <td>
                            <a href="{{ route('backoffice.products.show', ['cooperative' => $cooperative->getKey(), 'product' => $product->getKey()]) }}">
                                {{ $product->name }}
                            </a>
                        </td>
                        <td>{{ $product->is_active ? 'Actif' : 'Inactif' }}</td>
                        <td>
                            @can('update', $catalog)
                                <form method="POST" class="inline-form"
                                      action="{{ route('backoffice.catalogs.products.destroy', ['cooperative' => $cooperative->getKey(), 'catalog' => $catalog->getKey(), 'product' => $product->getKey()]) }}"
                                      onsubmit="return confirm('Retirer ce produit du catalogue ?');">
                                    @csrf
                                    @method('DELETE')
                                    <button class="button button-danger button-small" type="submit">
                                        Retirer
                                    </button>
                                </form>
                            @endcan
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="3" class="empty">Aucun produit dans ce catalogue.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection