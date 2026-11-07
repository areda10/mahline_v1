@extends('layouts.admin')

@section('title', 'Fiche utilisateur — '.$user->display_name)

@section('content')
<div class="page-header">
    <div>
        <h1>Fiche utilisateur</h1>
        <p class="muted">Consultation du compte et de ses rattachements dans MAHLINE.</p>
    </div>

    <div class="actions">
        <a class="button button-secondary" href="{{ route('backoffice.dashboard') }}">Retour à la liste</a>
        @if($canViewAssignments)
            <a class="button button-secondary" href="{{ route('backoffice.super-admins.cooperatives.index', $user) }}">Gérer les affectations</a>
        @endif
    </div>

    <div class="actions">

        @can('resendInvitation', $user)
            <form method="POST"
                action="{{ route('backoffice.users.resend-invitation', $user) }}"
                onsubmit="return confirm('Envoyer une nouvelle invitation à cet utilisateur ?');">
                @csrf
                <button class="button" type="submit">
                    Renvoyer l’invitation
                </button>
            </form>
        @endcan

        @can('activate', $user)
            <form method="POST" action="{{ route('backoffice.users.activate', $user) }}"
                  onsubmit="return confirm('Confirmer l’activation de ce compte ?');">
                @csrf
                @method('PATCH')
                <button class="button" type="submit">Activer le compte</button>
            </form>
        @endcan

        @can('deactivate', $user)
            <form method="POST" action="{{ route('backoffice.users.deactivate', $user) }}"
                  onsubmit="return confirm('Confirmer la désactivation de ce compte ? Les sessions et jetons API seront révoqués.');">
                @csrf
                @method('PATCH')
                <button class="button button-danger" type="submit">Désactiver le compte</button>
            </form>
        @endcan

        @can('suspend', $user)
            <form method="POST" action="{{ route('backoffice.users.suspend', $user) }}"
                  onsubmit="return confirm('Confirmer la suspension de ce compte ? Toutes ses sessions et tous ses jetons API seront révoqués.');">
                @csrf
                @method('PATCH')
                <button class="button button-danger" type="submit">Suspendre le compte</button>
            </form>
        @endcan

        @can('archive', $user)
            <form method="POST" action="{{ route('backoffice.users.archive', $user) }}"
                  onsubmit="return confirm('Confirmer l’archivage ? Les sessions et jetons API seront révoqués.');">
                @csrf
                @method('PATCH')
                <button class="button button-danger" type="submit">Archiver le compte</button>
            </form>
        @endcan

        @can('restore', $user)
            <form method="POST" action="{{ route('backoffice.users.restore', $user) }}"
                  onsubmit="return confirm('Restaurer ce compte en attente ? Une nouvelle activation sera nécessaire. Les anciennes sessions et les anciens jetons resteront révoqués.');">
                @csrf
                @method('PATCH')
                <button class="button" type="submit">Restaurer le compte</button>
            </form>
        @endcan

        @can('delete', $user)
            <form method="POST" action="{{ route('backoffice.users.destroy', $user) }}"
                  onsubmit="return confirm('Confirmer la suppression logique de ce compte ? Ses sessions et jetons API seront révoqués.');">
                @csrf
                @method('DELETE')
                <button class="button button-danger" type="submit">Supprimer le compte</button>
            </form>
        @endcan
    </div>
</div>

<section class="card" aria-labelledby="identity-heading">
    <h2 id="identity-heading">Identité et coordonnées</h2>

    <div class="table-wrap">
        <table>
            <tbody>
                <tr>
                    <th>Nom d'affichage</th>
                    <td>{{ $user->display_name }}</td>
                </tr>
                <tr>
                    <th>Prénom</th>
                    <td>{{ $user->first_name }}</td>
                </tr>
                <tr>
                    <th>Nom</th>
                    <td>{{ $user->last_name }}</td>
                </tr>
                <tr>
                    <th>Email</th>
                    <td>{{ $user->email }}</td>
                </tr>
                <tr>
                    <th>Téléphone</th>
                    <td>{{ $user->telephone }}</td>
                </tr>
            </tbody>
        </table>
    </div>
</section>

<section class="card" aria-labelledby="account-heading">
    <h2 id="account-heading">Compte et autorisations</h2>

    <div class="table-wrap">
        <table>
            <tbody>
                <tr>
                    <th>Statut</th>
                    <td><span class="badge">{{ $user->status->label() }}</span></td>
                </tr>
                <tr>
                    <th>Rôles</th>
                    <td>
                        @forelse($user->roles as $role)
                            <span class="badge">{{ $role->name }}</span>
                        @empty
                            —
                        @endforelse
                    </td>
                </tr>
                <tr>
                    <th>Coopérative principale</th>
                    <td>{{ $user->cooperative?->name ?? 'Aucune coopérative' }}</td>
                </tr>
                <tr>
                    <th>Compte créé le</th>
                    <td>{{ $user->created_at?->format('d/m/Y H:i') ?? '—' }}</td>
                </tr>
                <tr>
                    <th>Dernière modification</th>
                    <td>{{ $user->updated_at?->format('d/m/Y H:i') ?? '—' }}</td>
                </tr>
            </tbody>
        </table>
    </div>
</section>

@if($canViewAssignments)
    <section class="card" aria-labelledby="assignments-heading">
        <h2 id="assignments-heading">Affectations aux coopératives</h2>

        @if($user->cooperatives->isEmpty())
            <div class="empty">Ce super administrateur n'est affecté à aucune coopérative.</div>
        @else
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Coopérative</th>
                            <th>Statut</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($user->cooperatives as $cooperative)
                            <tr>
                                <td>{{ $cooperative->name }}</td>
                                <td>{{ ucfirst($cooperative->status->value) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>
@endif
@endsection
