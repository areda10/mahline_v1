@extends('layouts.root')
@section('title', 'Créer un catalogue')
@section('content')
<div class="page-header"><h1>Créer un catalogue</h1></div>
<div class="card">
<form method="POST" action="{{ route('root.backoffice.cooperatives.catalogs.store', ['cooperative' => $cooperative->getKey()]) }}">
@csrf
@include('super-admin-root.cooperatives.catalogs._form', ['submitLabel' => 'Créer le catalogue'])
</form>
</div>
@endsection
