@extends('layouts.admin')
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
<p class="muted">Consultation uniquement. La gestion des associations sera ajoutée dans une étape dédiée.</p>
<div class="table-wrap"><table>
<thead><tr><th>Produit</th><th>État</th></tr></thead>
<tbody>
@forelse($catalog->products as $product)
<tr><td>{{ $product->name }}</td><td>{{ $product->is_active ? 'Actif' : 'Inactif' }}</td></tr>
@empty
<tr><td colspan="2" class="empty">Aucun produit dans ce catalogue.</td></tr>
@endforelse
</tbody></table></div>
</div>
@endsection
