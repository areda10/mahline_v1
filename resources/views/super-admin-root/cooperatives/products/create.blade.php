@extends('layouts.root')

@section('title', 'Créer un produit')

@section('content')
<div class="page-header"><h1>Créer un produit</h1></div>
<div class="card">
    <form method="POST" action="{{ route('root.backoffice.cooperatives.products.store', ['cooperative' => $cooperative->getKey()]) }}">
        @csrf
        @include('super-admin-root.cooperatives.products._form', ['submitLabel' => 'Créer le produit'])
    </form>
</div>
@endsection
