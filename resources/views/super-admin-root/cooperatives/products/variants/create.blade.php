@extends('layouts.root')

@section('title', 'Ajouter une variante')

@section('content')
<div class="page-header">
    <div>
        <h1>Ajouter une variante</h1>
        <p class="muted">Produit : {{ $product->name }}</p>
    </div>
</div>
<div class="card">
    <form method="POST" action="{{ route('root.backoffice.cooperatives.products.variants.store', ['cooperative' => $cooperative->getKey(), 'product' => $product->getKey()]) }}">
        @csrf
        @include('super-admin-root.cooperatives.products.variants._form', ['submitLabel' => 'Créer la variante'])
    </form>
</div>
@endsection
