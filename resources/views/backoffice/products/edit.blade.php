@extends('layouts.admin')

@section('title', 'Modifier un produit')

@section('content')
<div class="page-header">
    <h1>Modifier le produit</h1>
</div>

<div class="card">
    <form method="POST"
          action="{{ route('backoffice.products.update', ['cooperative' => $cooperative->getKey(), 'product' => $product->getKey()]) }}">
        @csrf
        @method('PUT')
        @include('backoffice.products._form', ['submitLabel' => 'Enregistrer'])
    </form>
</div>
@endsection