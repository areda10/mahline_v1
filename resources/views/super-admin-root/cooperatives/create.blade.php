@extends('layouts.admin')
@section('title', 'Créer une coopérative')
@section('content')
<div class="page-header"><h1>Créer une coopérative</h1></div>
<div class="card"><form method="POST" action="{{ route('root.backoffice.cooperatives.store') }}">@csrf
@include('super-admin-root.cooperatives._form', ['submitLabel' => 'Créer la coopérative'])
</form></div>
@endsection
