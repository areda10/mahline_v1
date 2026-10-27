@extends('layouts.admin')

@section('title', 'Créer un produit')

@section('content')
<div class="page-header">
    <h1>Créer un produit</h1>
</div>

<div class="card">
    <form method="POST"
          action="{{ route('backoffice.products.store', ['cooperative' => $cooperative->getKey()]) }}">
        @csrf
        @include('backoffice.products._form', ['submitLabel' => 'Créer le produit'])
    </form>
</div>
@endsection