@extends('layouts.admin')

@section('title', 'Produits')

@section('content')
<div class="page-header">
    <div>
        <h1>Produits</h1>
        <p class="muted">Produits de {{ $cooperative->name }}.</p>
    </div>

    @can('create', [\App\Domains\Products\Models\Product::class, (string) $cooperative->getKey()])
        <a class="button"
           href="{{ route('backoffice.products.create', ['cooperative' => $cooperative->getKey()]) }}">
            Créer un produit
        </a>
    @endcan
</div>

<div class="card">
    @if($products->isEmpty())
        <div class="empty">Aucun produit pour le moment.</div>
    @else
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Nom</th>
                        <th>Catégories</th>
                        <th>Catalogues</th>
                        <th>État</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($products as $product)
                        <tr>
                            <td>
                                <strong>{{ $product->name }}</strong>
                                @if($product->description)
                                    <div class="muted">{{ $product->description }}</div>
                                @endif
                            </td>
                            <td>{{ $product->categories->pluck('name')->join(', ') ?: '—' }}</td>
                            <td>{{ $product->catalogs->pluck('name')->join(', ') ?: '—' }}</td>
                            <td>
                                <span class="badge {{ $product->is_active ? '' : 'badge-inactive' }}">
                                    {{ $product->is_active ? 'Actif' : 'Inactif' }}
                                </span>
                            </td>
                            <td>
                                <div class="actions">
                                    @can('view', $product)
                                        <a class="button button-secondary button-small"
                                           href="{{ route('backoffice.products.show', ['cooperative' => $cooperative->getKey(), 'product' => $product->getKey()]) }}">
                                            Voir
                                        </a>
                                    @endcan

                                    @can('update', $product)
                                        <a class="button button-secondary button-small"
                                           href="{{ route('backoffice.products.edit', ['cooperative' => $cooperative->getKey(), 'product' => $product->getKey()]) }}">
                                            Modifier
                                        </a>
                                    @endcan

                                    @can('delete', $product)
                                        <form method="POST" class="inline-form"
                                              action="{{ route('backoffice.products.destroy', ['cooperative' => $cooperative->getKey(), 'product' => $product->getKey()]) }}"
                                              onsubmit="return confirm('Supprimer ce produit ?');">
                                            @csrf
                                            @method('DELETE')
                                            <button class="button button-danger button-small" type="submit">
                                                Supprimer
                                            </button>
                                        </form>
                                    @endcan
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="pagination">{{ $products->links() }}</div>
    @endif
</div>
@endsection