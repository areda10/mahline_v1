@extends('layouts.admin')

@section('title', 'Modifier une catégorie')

@section('content')
<div class="page-header">
    <h1>Modifier la catégorie</h1>
</div>

<div class="card">
    @if($category->isGlobal())
        <div class="alert alert-error">
            Cette catégorie est globale. Seul un utilisateur autorisé par la
            Policy peut la modifier.
        </div>
    @endif

    <form method="POST"
          action="{{ route('backoffice.categories.update', ['cooperative' => $cooperative->getKey(), 'category' => $category->getKey()]) }}">
        @csrf
        @method('PUT')
        @include('backoffice.categories._form', ['submitLabel' => 'Enregistrer'])
    </form>
</div>
@endsection