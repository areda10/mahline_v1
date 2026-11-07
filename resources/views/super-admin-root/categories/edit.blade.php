@extends('layouts.root')

@section('title', 'Modifier une catégorie globale')

@section('content')
<div class="page-header"><h1>Modifier la catégorie globale</h1></div>
<div class="card">
    <form method="POST" action="{{ route('root.backoffice.categories.update', $category) }}">
        @csrf @method('PUT')
        @include('super-admin-root.categories._form', ['submitLabel' => 'Enregistrer'])
    </form>
</div>
@endsection
