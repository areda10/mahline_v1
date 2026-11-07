@extends('layouts.root')

@section('title', 'Affectations des coopératives')

@section('content')
<div class="page-header">
    <div>
        <h1>Affectations des coopératives</h1>
        <p class="muted">
            Gestion des coopératives accessibles à
            <strong>{{ $superAdmin->display_name }}</strong>
            ({{ $superAdmin->email }})
        </p>
    </div>
</div>

@if($superAdmin->status)
    <p class="muted">Statut du compte : {{ $superAdmin->status->value }}</p>
@endif

<section class="card" aria-labelledby="assigned-cooperatives-heading">
    <h2 id="assigned-cooperatives-heading">Coopératives affectées</h2>

    @if($cooperatives->isEmpty())
        <div class="empty">Aucune coopérative n'est actuellement affectée à ce super administrateur.</div>
    @else
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Coopérative</th>
                        <th>Adresse</th>
                        <th>Email</th>
                        <th>Téléphone</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($cooperatives as $cooperative)
                        <tr>
                            <td><strong>{{ $cooperative->name }}</strong></td>
                            <td>
                                @if($cooperative->address)
                                    {{ $cooperative->address->address_line_1 }}
                                    @if($cooperative->address->address_line_2)
                                        , {{ $cooperative->address->address_line_2 }}
                                    @endif
                                    — {{ $cooperative->address->city }}
                                    @if($cooperative->address->country)
                                        , {{ $cooperative->address->country->name }}
                                    @endif
                                @else
                                    —
                                @endif
                            </td>
                            <td>{{ $cooperative->email ?? '—' }}</td>
                            <td>{{ $cooperative->phone ?? '—' }}</td>
                            <td>
                                @can('unassignCooperative', [$superAdmin, $cooperative])
                                    <form method="POST"
                                          action="{{ route('root.backoffice.users.cooperatives.destroy', ['superAdmin' => $superAdmin, 'cooperative' => $cooperative]) }}"
                                          onsubmit="return confirm('Retirer cette affectation ?');">
                                        @csrf
                                        @method('DELETE')
                                        <button class="button button-danger button-small" type="submit">
                                            Retirer
                                        </button>
                                    </form>
                                @else
                                    <span class="muted">Non autorisé</span>
                                @endcan
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="pagination">
            {{ $cooperatives->links() }}
        </div>
    @endif
</section>

<section class="card" aria-labelledby="available-cooperatives-heading">
    <h2 id="available-cooperatives-heading">Coopératives disponibles</h2>
    <p class="muted">Ces coopératives ne sont pas encore affectées à ce super administrateur.</p>

    @if($availableCooperatives->isEmpty())
        <div class="empty">Aucune coopérative disponible pour une nouvelle affectation.</div>
    @else
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Coopérative</th>
                        <th>Adresse</th>
                        <th>Email</th>
                        <th>Téléphone</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($availableCooperatives as $cooperative)
                        <tr>
                            <td><strong>{{ $cooperative->name }}</strong></td>
                            <td>
                                @if($cooperative->address)
                                    {{ $cooperative->address->address_line_1 }}
                                    @if($cooperative->address->address_line_2)
                                        , {{ $cooperative->address->address_line_2 }}
                                    @endif
                                    — {{ $cooperative->address->city }}
                                    @if($cooperative->address->country)
                                        , {{ $cooperative->address->country->name }}
                                    @endif
                                @else
                                    —
                                @endif
                            </td>
                            <td>{{ $cooperative->email ?? '—' }}</td>
                            <td>{{ $cooperative->phone ?? '—' }}</td>
                            <td>
                                @can('assignCooperative', [$superAdmin, $cooperative])
                                    <form method="POST"
                                          action="{{ route('root.backoffice.users.cooperatives.store', ['superAdmin' => $superAdmin, 'cooperative' => $cooperative]) }}">
                                        @csrf
                                        <button class="button button-small" type="submit">
                                            Affecter
                                        </button>
                                    </form>
                                @else
                                    <span class="muted">Non autorisé</span>
                                @endcan
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="pagination">
            {{ $availableCooperatives->links() }}
        </div>
    @endif
</section>
@endsection
