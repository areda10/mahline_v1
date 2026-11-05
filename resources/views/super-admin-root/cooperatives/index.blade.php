@extends('layouts.admin')

@section('title', 'Coopératives')

@section('content')
<div class="page-header">
    <div><h1>Coopératives</h1><p class="muted">Gestion des coopératives accessibles selon vos autorisations.</p></div>
    @can('create', \App\Domains\Cooperatives\Models\Cooperative::class)
        <a class="button" href="{{ route('root.backoffice.cooperatives.create') }}">Créer une coopérative</a>
    @endcan
</div>
<div class="card">
    @if($cooperatives->isEmpty())
        <div class="empty">Aucune coopérative accessible.</div>
    @else
    <div class="table-wrap"><table><thead><tr><th>Nom</th><th>Email</th><th>Téléphone</th><th>Statut</th><th>Actions</th></tr></thead><tbody>
    @foreach($cooperatives as $cooperative)
        <tr>
            <td><strong>{{ $cooperative->name }}</strong></td><td>{{ $cooperative->email ?? '—' }}</td><td>{{ $cooperative->phone ?? '—' }}</td><td>{{ $cooperative->status->value }}</td>
            <td class="actions"><a class="button button-secondary button-small" href="{{ route('root.backoffice.cooperatives.show', $cooperative) }}">Consulter</a>
            @can('update', $cooperative)<a class="button button-secondary button-small" href="{{ route('root.backoffice.cooperatives.edit', $cooperative) }}">Modifier</a>@endcan</td>
        </tr>
    @endforeach
    </tbody></table></div><div class="pagination">{{ $cooperatives->links() }}</div>
    @endif
</div>
@endsection
