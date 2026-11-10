@extends('layouts.root')

@section('title', 'Fiche utilisateur — '.$user->display_name)

@section('content')
<div class="page-header">
    <div>
        <h1>Fiche utilisateur</h1>
        <p class="muted">Consultation du compte et de ses rattachements dans MAHLINE.</p>
    </div>

    <div class="actions">
        <a class="button button-secondary" href="{{ route('root.backoffice.dashboard') }}">Retour au tableau de bord</a>
        @unless($user->roles->contains('slug', 'super_admin_root'))
            <a class="button" href="{{ route('root.backoffice.users.edit', $user) }}">Modifier l’utilisateur</a>
        @endunless
        @if($canViewAssignments)
            <a class="button" href="{{ route('root.backoffice.users.cooperatives.index', $user) }}">Gérer les affectations coopératives</a>
        @endif
    </div>

    <div class="actions">

        @can('resendInvitation', $user)
            <form method="POST"
                action="{{ route('root.backoffice.users.resend-invitation', $user) }}"
                onsubmit="return confirm('Envoyer une nouvelle invitation à cet utilisateur ?');">
                @csrf
                <button class="button" type="submit">
                    Renvoyer l’invitation
                </button>
            </form>
        @endcan

        @can('activate', $user)
            <form method="POST" action="{{ route('root.backoffice.users.activate', $user) }}"
                  onsubmit="return confirm('Activer le compte et envoyer un lien de vérification ? L’utilisateur devra définir son mot de passe via le lien reçu.');">
                @csrf
                @method('PATCH')
                <button class="button" type="submit">Activer et envoyer une invitation</button>
            </form>

            <form method="POST" action="{{ route('root.backoffice.users.activate-and-verify', $user) }}"
                  onsubmit="return confirm('Activer immédiatement et vérifier l’e-mail ? Le mot de passe provisoire sera MahlineMahline et son changement sera obligatoire à la première connexion.');">
                @csrf
                @method('PATCH')
                <button class="button" type="submit">Activer, vérifier l’e-mail et autoriser la connexion</button>
            </form>
        @endcan

        @can('deactivate', $user)
            <form method="POST" action="{{ route('root.backoffice.users.deactivate', $user) }}"
                  onsubmit="return confirm('Confirmer la désactivation de ce compte ? Les sessions et jetons API seront révoqués.');">
                @csrf
                @method('PATCH')
                <button class="button button-danger" type="submit">Désactiver le compte</button>
            </form>
        @endcan

        @can('suspend', $user)
            <form method="POST" action="{{ route('root.backoffice.users.suspend', $user) }}"
                  onsubmit="return confirm('Confirmer la suspension de ce compte ? Toutes ses sessions et tous ses jetons API seront révoqués.');">
                @csrf
                @method('PATCH')
                <button class="button button-danger" type="submit">Suspendre le compte</button>
            </form>
        @endcan

        @can('archive', $user)
            <form method="POST" action="{{ route('root.backoffice.users.archive', $user) }}"
                  onsubmit="return confirm('Confirmer l’archivage ? Les sessions et jetons API seront révoqués.');">
                @csrf
                @method('PATCH')
                <button class="button button-danger" type="submit">Archiver le compte</button>
            </form>
        @endcan

        @can('restore', $user)
            <form method="POST" action="{{ route('root.backoffice.users.restore', $user) }}"
                  onsubmit="return confirm('Restaurer ce compte en attente ? Une nouvelle activation sera nécessaire. Les anciennes sessions et les anciens jetons resteront révoqués.');">
                @csrf
                @method('PATCH')
                <button class="button" type="submit">Restaurer le compte en attente</button>
            </form>
        @endcan



        @can('delete', $user)
            <form method="POST" action="{{ route('root.backoffice.users.destroy', $user) }}"
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

@if($sessions->isNotEmpty())
    <section class="card" aria-labelledby="sessions-heading">
        <h2 id="sessions-heading">Appareils connectés de cet utilisateur</h2>
        <p class="muted">Seules les sessions encore actives selon le délai d’inactivité propre aux rôles de cet utilisateur sont affichées. Vous pouvez révoquer chaque appareil individuellement.</p>
        <div class="table-wrap">
            <table>
                <thead><tr><th>Appareil</th><th>Navigateur</th><th>Adresse IP</th><th>Dernière activité</th><th>État</th><th>Action</th></tr></thead>
                <tbody>
                    @foreach($sessions as $session)
                        <tr>
                            <td>{{ $session->device ?: 'Appareil inconnu' }}</td>
                            <td>{{ $session->browser ?: '—' }}</td>
                            <td>{{ $session->ip_address ?: '—' }}</td>
                            <td>{{ $session->last_activity_at?->format('d/m/Y H:i') ?? '—' }}</td>
                            <td>{{ $session->is_current ? 'Session actuelle' : 'Connecté' }}</td>
                            <td>
                                <form method="POST" action="{{ route('root.backoffice.users.sessions.destroy', ['user' => $user, 'session' => $session]) }}" onsubmit="return confirm('Révoquer cette session ?');">
                                    @csrf
                                    @method('DELETE')
                                    <button class="button button-danger button-small" type="submit">Révoquer</button>
                                </form>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </section>
@else
    <section class="card" aria-labelledby="sessions-heading">
        <h2 id="sessions-heading">Appareils connectés</h2>
        <div class="empty">Aucune session active.</div>
    </section>
@endif

<section class="card" aria-labelledby="api-tokens-heading">
    <div class="page-header">
        <div>
            <h2 id="api-tokens-heading">Clés API de l’utilisateur</h2>
            <p class="muted">Consultation et révocation des clés API de tous les rôles. La révocation des clés API ne modifie pas les sessions Web.</p>
        </div>
        @if($apiTokens->contains(fn ($token) => $token->revoked_at === null))
            <form method="POST"
                  action="{{ route('root.backoffice.users.api-tokens.destroy-all', $user) }}"
                  onsubmit="return confirm('Révoquer toutes les clés API de cet utilisateur ? Ses sessions Web resteront actives.');">
                @csrf
                @method('DELETE')
                <button class="button button-danger" type="submit">Révoquer toutes les clés API</button>
            </form>
        @endif
    </div>

    @if($apiTokens->isEmpty())
        <div class="empty">Aucune clé API créée pour cet utilisateur.</div>
    @else
        <div class="table-wrap">
            <table>
                <thead>
                    <tr><th>Nom</th><th>Créée le</th><th>Dernière utilisation</th><th>Expiration</th><th>État</th><th>Action</th></tr>
                </thead>
                <tbody>
                    @foreach($apiTokens as $token)
                        @php
                            $active = $token->revoked_at === null
                                && ($token->expires_at === null || $token->expires_at->isFuture());
                        @endphp
                        <tr>
                            <td>{{ $token->name }}</td>
                            <td>{{ $token->created_at?->format('d/m/Y H:i') ?? '—' }}</td>
                            <td>{{ $token->last_used_at?->format('d/m/Y H:i') ?? 'Jamais' }}</td>
                            <td>{{ $token->expires_at?->format('d/m/Y H:i') ?? 'Aucune' }}</td>
                            <td>{{ $active ? 'Active' : ($token->revoked_at ? 'Révoquée' : 'Expirée') }}</td>
                            <td>
                                @if($token->revoked_at === null)
                                    <form method="POST"
                                          action="{{ route('root.backoffice.users.api-tokens.destroy', ['user' => $user, 'token' => $token]) }}"
                                          onsubmit="return confirm('Révoquer cette clé API ?');">
                                        @csrf
                                        @method('DELETE')
                                        <button class="button button-danger button-small" type="submit">Révoquer</button>
                                    </form>
                                @else
                                    —
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
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
