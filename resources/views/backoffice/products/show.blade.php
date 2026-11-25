@extends('layouts.admin')

@section('title', $product->name)

@section('content')
<div class="page-header">
    <div>
        <h1>{{ $product->name }}</h1>
        <p class="muted">{{ $product->description }}</p>
    </div>

    <div class="actions">
        <a class="button button-secondary"
           href="{{ route('backoffice.dashboard') }}">
            Retour
        </a>

        @can('update', $product)
            <a class="button"
               href="{{ route('backoffice.products.edit', ['cooperative' => $cooperative->getKey(), 'product' => $product->getKey()]) }}">
                Modifier
            </a>
        @endcan
    </div>
</div>

<div class="card">
    @if(($product->product_type ?? 'simple') === 'variant')
        <p><strong>Type :</strong> Produit avec variantes</p>
    @else
        <p><strong>Prix :</strong> {{ $product->price !== null ? number_format((float) $product->price, 2, ',', ' ') . ' MAD' : 'Non défini' }}</p>
    @endif
    <p><strong>Slug :</strong> {{ $product->slug }}</p>
    <p>
        <strong>État :</strong>
        <span class="badge {{ $product->is_active ? '' : 'badge-inactive' }}">
            {{ $product->is_active ? 'Actif' : 'Inactif' }}
        </span>
    </p>

    <h2>Catégories</h2>
    @forelse($product->categories as $category)
        <span class="badge">{{ $category->name }}</span>
    @empty
        <p class="muted">Aucune catégorie associée.</p>
    @endforelse

    <h2>Catalogues</h2>
    @forelse($product->catalogs as $catalog)
        <p>
            <a href="{{ route('backoffice.catalogs.show', ['cooperative' => $cooperative->getKey(), 'catalog' => $catalog->getKey()]) }}">
                {{ $catalog->name }}
            </a>
        </p>
    @empty
        <p class="muted">Aucun catalogue associé.</p>
    @endforelse
</div>

@if(($product->product_type ?? 'simple') === 'variant')
<div class="card">
    <div class="page-header">
        <h2>Variantes</h2>
        @can('update', $product)
            @if($product->is_active)
                <a class="button" href="{{ route('backoffice.products.variants.create', ['cooperative' => $cooperative->getKey(), 'product' => $product->getKey()]) }}">Ajouter une variante</a>
            @endif
        @endcan
    </div>
    @forelse($product->variants as $variant)
        <div class="variant-row" style="border-top:1px solid #e5e7eb;padding:12px 0">
            <strong>{{ $variant->label }}</strong>
            <span>{{ number_format((float) $variant->price, 2, ',', ' ') }} MAD</span>
            <span class="badge {{ $variant->trashed() || !$variant->is_active ? 'badge-inactive' : '' }}">{{ $variant->trashed() ? 'Supprimée' : ($variant->is_active ? 'Active' : 'Inactive') }}</span>
            @if(!$variant->trashed())
                @can('update', $product)
                    <a class="button button-secondary" href="{{ route('backoffice.products.variants.edit', ['cooperative' => $cooperative->getKey(), 'product' => $product->getKey(), 'variant' => $variant->getKey()]) }}">Modifier</a>
                    @if($variant->is_active)
                        <form method="POST" action="{{ route('backoffice.products.variants.deactivate', ['cooperative' => $cooperative->getKey(), 'product' => $product->getKey(), 'variant' => $variant->getKey()]) }}">@csrf @method('PATCH')<button class="button button-secondary" type="submit">Désactiver</button></form>
                    @else
                        <form method="POST" action="{{ route('backoffice.products.variants.activate', ['cooperative' => $cooperative->getKey(), 'product' => $product->getKey(), 'variant' => $variant->getKey()]) }}">@csrf @method('PATCH')<button class="button button-secondary" type="submit">Activer</button></form>
                    @endif
                    <form method="POST" action="{{ route('backoffice.products.variants.destroy', ['cooperative' => $cooperative->getKey(), 'product' => $product->getKey(), 'variant' => $variant->getKey()]) }}" onsubmit="return confirm('Supprimer cette variante ?');">@csrf @method('DELETE')<button class="button button-danger" type="submit">Supprimer</button></form>
                @endcan
            @else
                @can('restore', $product)
                    <form method="POST" action="{{ route('backoffice.products.variants.restore', ['cooperative' => $cooperative->getKey(), 'product' => $product->getKey(), 'variant' => $variant->getKey()]) }}">@csrf @method('PATCH')<button class="button button-secondary" type="submit">Restaurer</button></form>
                @endcan
            @endif
        </div>
    @empty
        <p class="muted">Aucune variante enregistrée.</p>
    @endforelse
</div>
@endif

<div class="card">
    <h2>Actions</h2>

    @can('update', $product)
        @if(!$product->is_active)
            <form method="POST"
                  action="{{ route('backoffice.products.reactivate', ['cooperative' => $cooperative->getKey(), 'product' => $product->getKey()]) }}">
                @csrf
                @method('PATCH')
                <button class="button" type="submit">Réactiver le produit</button>
            </form>
        @endif
    @endcan

    @can('delete', $product)
        <form method="POST" style="margin-top:12px"
              action="{{ route('backoffice.products.destroy', ['cooperative' => $cooperative->getKey(), 'product' => $product->getKey()]) }}"
              onsubmit="return confirm('Supprimer ce produit ?');">
            @csrf
            @method('DELETE')
            <button class="button button-danger" type="submit">Supprimer le produit</button>
        </form>
    @endcan
</div>
@endsection