@extends('layouts.admin')
@section('title', 'Créer une coopérative')
@section('content')
<div class="page-header"><h1>Créer une coopérative</h1></div>
<div class="card"><form method="POST" action="{{ route('backoffice.cooperatives.store') }}">@csrf
@include('backoffice.cooperatives._form', ['submitLabel' => 'Créer la coopérative'])
</form></div>
@endsection
