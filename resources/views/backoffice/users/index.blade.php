@extends('layouts.admin')

@section('title', 'Utilisateurs')

@section('content')
<div class="page-header">
    <div>
        <h1>Utilisateurs</h1>
        <p class="muted">Vue globale de tous les comptes MAHLINE, indépendamment de leurs affectations aux coopératives.</p>
    </div>
</div>

<section class="card" aria-labelledby="users-filters-heading">
    <h2 id="users-filters-heading">Recherche et filtres</h2>

    <form method="GET" action="{{ route('backoffice.users.index') }}">
        <div class="field">
            <label for="search">Recherche</label>
            <input
                id="search"
                type="text"
                name="search"
                value="{{ $filters['search'] }}"
                placeholder="Nom, prénom, email ou téléphone"
            >
        </div>

        <div class="field">
            <label for="role">Rôle</label>
            <select id="role" name="role">
                <option value="">Tous les rôles</option>
                @foreach($roles as $role)
                    <option value="{{ $role->slug }}" @selected($filters['role'] === $role->slug)>
                        {{ $role->name }}
                    </option>
                @endforeach
            </select>
        </div>

        <div class="field">
            <label for="status">Statut</label>
            <select id="status" name="status">
                <option value="">Tous les statuts</option>
                @foreach($statuses as $status)
                    <option value="{{ $status->value }}" @selected($filters['status'] === $status->value)>
                        {{ $status->label() }}
                    </option>
                @endforeach
            </select>
        </div>

        <div class="actions">
            <button class="button" type="submit">Filtrer</button>
            <a class="button button-secondary" href="{{ route('backoffice.users.index') }}">Réinitialiser</a>
        </div>
    </form>
</section>

<section class="card" aria-labelledby="users-list-heading">
    <div class="page-header">
        <h2 id="users-list-heading">Tous les utilisateurs</h2>
        <span class="muted">{{ $users->total() }} résultat(s)</span>
    </div>

    @if($users->isEmpty())
        <div class="empty">Aucun utilisateur ne correspond à votre recherche.</div>
    @else
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Utilisateur</th>
                        <th>Email</th>
                        <th>Téléphone</th>
                        <th>Rôle(s)</th>
                        <th>Coopérative</th>
                        <th>Statut</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($users as $user)
                        <tr>
                            <td>
                                <a href="{{ route('backoffice.users.show', $user) }}">
                                    <strong>{{ $user->display_name }}</strong>
                                </a>
                                <div class="muted">{{ $user->first_name }} {{ $user->last_name }}</div>
                            </td>
                            <td>{{ $user->email }}</td>
                            <td>{{ $user->telephone }}</td>
                            <td>
                                @forelse($user->roles as $role)
                                    <span class="badge">{{ $role->name }}</span>
                                @empty
                                    —
                                @endforelse
                            </td>
                            <td>{{ $user->cooperative?->name ?? 'Aucune coopérative' }}</td>
                            <td><span class="badge">{{ $user->status->label() }}</span></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="pagination">
            {{ $users->links() }}
        </div>
    @endif
</section>
@endsection
