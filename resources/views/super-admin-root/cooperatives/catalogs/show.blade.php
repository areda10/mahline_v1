@extends('layouts.root')
@section('title', $catalog->name)
@section('content')
<div class="page-header">
<div><h1>{{ $catalog->name }}</h1><p class="muted">{{ $catalog->description }}</p></div>
<div class="actions">
<a class="button button-secondary" href="{{ route('root.backoffice.cooperatives.catalogs.index', ['cooperative' => $cooperative->getKey()]) }}">Retour</a>
@can('update', $catalog)<a class="button" href="{{ route('root.backoffice.cooperatives.catalogs.edit', ['cooperative' => $cooperative->getKey(), 'catalog' => $catalog->getKey()]) }}">Modifier</a>@endcan
</div>
</div>
<div class="card">
<p><strong>Coopérative :</strong> {{ $cooperative->name }}</p>
<p><strong>Slug :</strong> {{ $catalog->slug }}</p>
<p><strong>État :</strong> {{ $catalog->is_active ? 'Actif' : 'Inactif' }}</p>
</div>
<div class="card">
<div class="page-header"><h2>Produits associés</h2></div>
@can('update', $catalog)
@if($catalog->is_active && $availableProducts->isNotEmpty())
<form method="POST" action="{{ route('root.backoffice.cooperatives.catalogs.products.store', ['cooperative' => $cooperative->getKey(), 'catalog' => $catalog->getKey()]) }}" class="actions">
    @csrf
    <div class="field">
        <label for="product_id">Ajouter un produit actif</label>
        <select id="product_id" name="product_id" required>
            <option value="">Sélectionner un produit</option>
            @foreach($availableProducts as $product)
                <option value="{{ $product->getKey() }}" @selected(old('product_id') == $product->getKey())>{{ $product->name }}</option>
            @endforeach
        </select>
        @error('product_id') <div class="error">{{ $message }}</div> @enderror
    </div>
    <button class="button" type="submit">Ajouter au catalogue</button>
</form>
@elseif(!$catalog->is_active)
<p class="muted">Un catalogue inactif ne peut pas recevoir de nouveaux produits.</p>
@else
<p class="muted">Aucun autre produit actif disponible dans cette coopérative.</p>
@endif
@endcan
<div class="table-wrap"><table>
<thead><tr><th>Produit</th><th>État</th><th>Action</th></tr></thead>
<tbody>
@forelse($catalog->products as $product)
<tr>
<td>{{ $product->name }}</td>
<td>{{ $product->is_active ? 'Actif' : 'Inactif' }}</td>
<td>
@can('update', $catalog)
<form method="POST" action="{{ route('root.backoffice.cooperatives.catalogs.products.destroy', ['cooperative' => $cooperative->getKey(), 'catalog' => $catalog->getKey(), 'product' => $product->getKey()]) }}" onsubmit="return confirm('Retirer ce produit du catalogue ?');">
@csrf @method('DELETE')
<button class="button button-danger button-small" type="submit">Retirer</button>
</form>
@endcan
</td>
</tr>
@empty
<tr><td colspan="3" class="empty">Aucun produit dans ce catalogue.</td></tr>
@endforelse
</tbody></table></div>
</div>
@endsection
