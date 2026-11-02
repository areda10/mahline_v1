@extends('layouts.admin')
@section('title', $cooperative->name)
@section('content')
<div class="page-header"><div><h1>{{ $cooperative->name }}</h1><p class="muted">Statut : {{ $cooperative->status->value }}</p></div>
@can('update', $cooperative)<a class="button" href="{{ route('backoffice.cooperatives.edit', $cooperative) }}">Modifier</a>@endcan</div>
<div class="card"><dl>
<dt>Email</dt><dd>{{ $cooperative->email ?? '—' }}</dd><dt>Téléphone</dt><dd>{{ $cooperative->phone ?? '—' }}</dd>
<dt>Adresse (ID)</dt><dd>{{ $cooperative->address_id ?? '—' }}</dd><dt>RC</dt><dd>{{ $cooperative->rc ?? '—' }}</dd><dt>ICE</dt><dd>{{ $cooperative->ice ?? '—' }}</dd>
</dl>
@can('update', $cooperative)
<div class="actions">
@foreach(['activate' => 'Activer', 'deactivate' => 'Désactiver', 'suspend' => 'Suspendre', 'archive' => 'Archiver'] as $action => $label)
<form method="POST" action="{{ route('backoffice.cooperatives.'.$action, $cooperative) }}">@csrf @method('PATCH')<button class="button button-secondary" type="submit">{{ $label }}</button></form>
@endforeach
</div>
@endcan
@can('delete', $cooperative)
<form method="POST" action="{{ route('backoffice.cooperatives.destroy', $cooperative) }}" onsubmit="return confirm('Supprimer cette coopérative ?');">@csrf @method('DELETE')<button class="button button-danger" type="submit">Supprimer</button></form>
@endcan
</div>
@endsection
