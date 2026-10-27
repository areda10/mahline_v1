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
           href="{{ route('backoffice.products.index', ['cooperative' => $cooperative->getKey()]) }}">
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