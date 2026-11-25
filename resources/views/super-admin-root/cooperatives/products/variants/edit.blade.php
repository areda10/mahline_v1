@extends('layouts.root')

@section('title', 'Modifier une variante')

@section('content')
<div class="page-header">
    <div>
        <h1>Modifier la variante</h1>
        <p class="muted">Produit : {{ $product->name }}</p>
    </div>
</div>
<div class="card">
    <form method="POST" action="{{ route('root.backoffice.cooperatives.products.variants.update', ['cooperative' => $cooperative->getKey(), 'product' => $product->getKey(), 'variant' => $variant->getKey()]) }}">
        @csrf @method('PUT')
        @include('super-admin-root.cooperatives.products.variants._form', ['submitLabel' => 'Enregistrer les modifications'])
    </form>
</div>
@endsection
