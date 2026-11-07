@extends('layouts.root')

@section('title', 'Créer une catégorie')

@section('content')
<div class="page-header">
    <h1>Créer une catégorie locale</h1>
</div>

<div class="card">
    <p class="muted">
        Cette page crée une catégorie pour la coopérative sélectionnée.
        Elle ne crée pas de catégorie globale MAHLINE.
    </p>

    <form method="POST"
          action="{{ route('root.backoffice.cooperatives.categories.store', ['cooperative' => $cooperative->getKey()]) }}">
        @csrf
        @include('super-admin-root.cooperatives.categories._form', ['submitLabel' => 'Créer la catégorie'])
    </form>
</div>
@endsection