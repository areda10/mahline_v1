@extends('layouts.admin')
@section('title', $cooperative->name)
@section('content')
<div class="page-header"><div><h1>{{ $cooperative->name }}</h1><p class="muted">Statut : {{ $cooperative->status->value }}</p></div>
@can('update', $cooperative)<a class="button" href="{{ route('root.backoffice.cooperatives.edit', $cooperative) }}">Modifier</a>@endcan</div>
<div class="card"><dl>
<dt>Email</dt><dd>{{ $cooperative->email ?? '—' }}</dd>
<dt>Téléphone</dt><dd>{{ $cooperative->phone ?? '—' }}</dd>
<dt>Adresse</dt>
<dd>
    @if($cooperative->address)
        {{ $cooperative->address->address_line_1 }}
        @if($cooperative->address->address_line_2), {{ $cooperative->address->address_line_2 }}@endif
        — {{ $cooperative->address->city }}
        @if($cooperative->address->country), {{ $cooperative->address->country->name }}@endif
    @else
        —
    @endif
</dd>
<dt>RC</dt><dd>{{ $cooperative->rc ?? '—' }}</dd>
<dt>ICE</dt><dd>{{ $cooperative->ice ?? '—' }}</dd>
</dl>
@if(auth()->user()->hasAnyRole(['super_admin_root', 'super_admin']) && auth()->user()->hasPermission('cooperatives.update'))
<div class="actions">
    @if($cooperative->status->value === 'pending')
        @foreach(['activate' => 'Activer', 'archive' => 'Archiver'] as $action => $label)
            <form method="POST" action="{{ route('root.backoffice.cooperatives.'.$action, $cooperative) }}">@csrf @method('PATCH')<button class="button button-secondary" type="submit">{{ $label }}</button></form>
        @endforeach
    @elseif($cooperative->status->value === 'active')
        @foreach(['deactivate' => 'Désactiver', 'suspend' => 'Suspendre'] as $action => $label)
            <form method="POST" action="{{ route('root.backoffice.cooperatives.'.$action, $cooperative) }}">@csrf @method('PATCH')<button class="button button-secondary" type="submit">{{ $label }}</button></form>
        @endforeach
    @elseif($cooperative->status->value === 'suspended')
        @foreach(['activate' => 'Activer', 'deactivate' => 'Désactiver', 'archive' => 'Archiver'] as $action => $label)
            <form method="POST" action="{{ route('root.backoffice.cooperatives.'.$action, $cooperative) }}">@csrf @method('PATCH')<button class="button button-secondary" type="submit">{{ $label }}</button></form>
        @endforeach
    @elseif($cooperative->status->value === 'inactive')
        @foreach(['activate' => 'Activer', 'archive' => 'Archiver'] as $action => $label)
            <form method="POST" action="{{ route('root.backoffice.cooperatives.'.$action, $cooperative) }}">@csrf @method('PATCH')<button class="button button-secondary" type="submit">{{ $label }}</button></form>
        @endforeach
    @elseif($cooperative->status->value === 'archived')
        @foreach(['activate' => 'Réactiver', 'deactivate' => 'Réactiver (inactive)'] as $action => $label)
            <form method="POST" action="{{ route('root.backoffice.cooperatives.'.$action, $cooperative) }}">@csrf @method('PATCH')<button class="button button-secondary" type="submit">{{ $label }}</button></form>
        @endforeach
    @endif
</div>
@endif
@can('delete', $cooperative)
<form method="POST" action="{{ route('root.backoffice.cooperatives.destroy', $cooperative) }}" onsubmit="return confirm('Supprimer cette coopérative ?');">@csrf @method('DELETE')<button class="button button-danger" type="submit">Supprimer</button></form>
@endcan
</div>
@endsection
