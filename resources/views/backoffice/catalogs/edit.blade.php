@extends('layouts.admin')

@section('title', 'Modifier un catalogue')

@section('content')
<div class="page-header">
    <h1>Modifier le catalogue</h1>
</div>

<div class="card">
    <form method="POST"
          action="{{ route('backoffice.catalogs.update', ['cooperative' => $cooperative->getKey(), 'catalog' => $catalog->getKey()]) }}">
        @csrf
        @method('PUT')
        @include('backoffice.catalogs._form', ['submitLabel' => 'Enregistrer'])
    </form>
</div>
@endsection