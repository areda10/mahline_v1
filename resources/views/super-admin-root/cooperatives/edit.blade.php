@extends('layouts.admin')
@section('title', 'Modifier une coopérative')
@section('content')
<div class="page-header"><h1>Modifier {{ $cooperative->name }}</h1></div>
<div class="card"><form method="POST" action="{{ route('root.backoffice.cooperatives.update', $cooperative) }}">@csrf @method('PUT')
@include('super-admin-root.cooperatives._form', ['submitLabel' => 'Enregistrer'])
</form></div>
@endsection
