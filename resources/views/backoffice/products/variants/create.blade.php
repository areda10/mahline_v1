@extends('layouts.admin')

@section('title', 'Ajouter une variante')

@section('content')
<div class="page-header">
    <div>
        <h1>Ajouter une variante</h1>
        <p class="muted">Produit : {{ $product->name }}</p>
    </div>
</div>
<div class="card">
    <form method="POST" action="{{ route('backoffice.products.variants.store', ['cooperative' => $cooperative->getKey(), 'product' => $product->getKey()]) }}">
        @csrf
        @include('backoffice.products.variants._form', ['submitLabel' => 'Créer la variante'])
    </form>
</div>
@endsection
