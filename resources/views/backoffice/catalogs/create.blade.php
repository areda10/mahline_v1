@extends('layouts.admin')

@section('title', 'Créer un catalogue')

@section('content')
<div class="page-header">
    <h1>Créer un catalogue</h1>
</div>

<div class="card">
    <form method="POST"
          action="{{ route('backoffice.catalogs.store', ['cooperative' => $cooperative->getKey()]) }}">
        @csrf
        @include('backoffice.catalogs._form', ['submitLabel' => 'Créer le catalogue'])
    </form>
</div>
@endsection