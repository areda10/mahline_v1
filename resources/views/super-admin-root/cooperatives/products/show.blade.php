@extends('layouts.root')

@section('title', $product->name)

@section('content')
<div class="page-header">
    <div><h1>{{ $product->name }}</h1><p class="muted">{{ $product->description }}</p></div>
    <div class="actions">
        <a class="button button-secondary" href="{{ route('root.backoffice.cooperatives.products.index', ['cooperative' => $cooperative->getKey()]) }}">Retour aux produits</a>
        @can('update', $product)
            <a class="button" href="{{ route('root.backoffice.cooperatives.products.edit', ['cooperative' => $cooperative->getKey(), 'product' => $product->getKey()]) }}">Modifier</a>
        @endcan
    </div>
</div>

<div class="card">
    <p><strong>Coopérative :</strong> {{ $cooperative->name }}</p>
    <p><strong>Type :</strong> {{ ($product->product_type ?? 'simple') === 'variant' ? 'Produit avec variantes' : 'Produit simple' }}</p>
    @if(($product->product_type ?? 'simple') === 'simple')
        <p><strong>Prix :</strong> {{ $product->price !== null ? number_format((float) $product->price, 2, ',', ' ') . ' MAD' : 'Non défini' }}</p>
        <p><strong>Unité :</strong> {{ $product->unit?->label ?? 'Non définie' }}</p>
    @endif
    <p><strong>Slug :</strong> {{ $product->slug }}</p>
    <p><strong>État :</strong> <span class="badge {{ $product->is_active ? '' : 'badge-inactive' }}">{{ $product->is_active ? 'Actif' : 'Inactif' }}</span></p>

    <h2>Catégories</h2>
    @forelse($product->categories as $category)
        <span class="badge">{{ $category->name }}</span>
    @empty
        <p class="muted">Aucune catégorie associée.</p>
    @endforelse

    <h2>Catalogues</h2>
    @forelse($product->catalogs as $catalog)
        <p><a href="{{ route('root.backoffice.cooperatives.catalogs.show', ['cooperative' => $cooperative->getKey(), 'catalog' => $catalog->getKey()]) }}">{{ $catalog->name }}</a></p>
    @empty
        <p class="muted">Aucun catalogue associé.</p>
    @endforelse
</div>

@if(($product->product_type ?? 'simple') === 'variant')
<div class="card">
    <h2>Variantes</h2>
    @forelse($product->variants()->with('unit')->orderBy('created_at')->get() as $variant)
        <div style="padding:12px 0;border-bottom:1px solid #444">
            <strong>{{ $variant->label }}</strong>
            <p>Prix : {{ number_format((float) $variant->effectivePrice(), 2, ',', ' ') }} MAD</p>
            <p>Unité : {{ $variant->unit?->label ?? 'Non définie' }}</p>
            @if($variant->sku)<p>SKU : {{ $variant->sku }}</p>@endif
            <p>État : {{ $variant->is_active ? 'Active' : 'Inactive' }}</p>
        </div>
    @empty
        <p class="muted">Aucune variante enregistrée.</p>
    @endforelse
</div>
@endif

<div class="card">
    <h2>Actions</h2>
    @can('reactivate', $product)
        @if(!$product->is_active)
            <form method="POST" action="{{ route('root.backoffice.cooperatives.products.reactivate', ['cooperative' => $cooperative->getKey(), 'product' => $product->getKey()]) }}">
                @csrf @method('PATCH')
                <button class="button" type="submit">Réactiver le produit</button>
            </form>
        @endif
    @endcan
    @can('delete', $product)
        <form method="POST" style="margin-top:12px" action="{{ route('root.backoffice.cooperatives.products.destroy', ['cooperative' => $cooperative->getKey(), 'product' => $product->getKey()]) }}" onsubmit="return confirm('Supprimer ce produit ?');">
            @csrf @method('DELETE')
            <button class="button button-danger" type="submit">Supprimer le produit</button>
        </form>
    @endcan
</div>
@endsection
