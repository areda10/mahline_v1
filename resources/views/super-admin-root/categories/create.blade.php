@extends('layouts.root')

@section('title', 'Créer une catégorie globale')

@section('content')
<div class="page-header"><h1>Créer une catégorie globale</h1></div>
<div class="card">
    <p class="muted">Cette catégorie sera disponible pour toutes les coopératives.</p>
    <form method="POST" action="{{ route('root.backoffice.categories.store') }}">
        @csrf
        @include('super-admin-root.categories._form', ['submitLabel' => 'Créer la catégorie'])
    </form>
</div>
@endsection
