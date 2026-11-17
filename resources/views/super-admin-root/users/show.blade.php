@extends('layouts.root')

@section('title', 'Fiche utilisateur — '.$user->display_name)

@section('content')

@if(session('success'))
    <div class="alert alert-success" role="status">{{ session('success') }}</div>
@endif
@if(session('error'))
    <div class="alert alert-danger" role="alert">{{ session('error') }}</div>
@endif

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

@can('updateUserAddress', $user)
<section class="card" aria-labelledby="personal-address-heading">
    <h2 id="personal-address-heading">Adresse personnelle</h2>
    <p class="muted">Modification soumise aux autorisations de la politique d’adresse. Pays fixé à Maroc pour la V1.</p>
    <form method="POST" action="{{ route('root.backoffice.users.address.update', $user) }}">
        @csrf
        @method('PUT')
        <div class="form-grid">
            <label>Numéro<input name="number" maxlength="50" value="{{ old('number', $user->primaryAddress?->number) }}"></label>
            <label>Rue<input name="street" required maxlength="255" value="{{ old('street', $user->primaryAddress?->street) }}"></label>
            <label>Étage / Complément<input name="floor" maxlength="50" value="{{ old('floor', $user->primaryAddress?->floor) }}"></label>
            <label>Ville<input name="city" required maxlength="120" value="{{ old('city', $user->primaryAddress?->city) }}"></label>
            <label>Région
                <select name="region" required>
                    <option value="">Sélectionner une région</option>
                    @foreach ($regions as $region)
                        <option value="{{ $region }}" @selected(old('region', $user->primaryAddress?->region) === $region)>{{ $region }}</option>
                    @endforeach
                </select>
            </label>
            <label>Code postal<input name="postal_code" maxlength="20" value="{{ old('postal_code', $user->primaryAddress?->postal_code) }}"></label>
            <label>Pays<input value="Maroc" readonly></label>
        </div>
        <button class="button" type="submit">Enregistrer l’adresse</button>
    </form>
</section>
@endcan

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
    @if(session('new_api_token'))
        <div class="alert alert-success" role="status">
            <p>Copiez cette clé maintenant. Elle ne sera plus affichée après avoir quitté cette page.</p>
            <textarea rows="3" readonly onclick="this.select()">{{ session('new_api_token') }}</textarea>
        </div>
    @endif

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

    @if($user->isAuthorized() && $activeApiTokenCount < $apiTokenLimit)
        <form method="POST" action="{{ route('root.backoffice.users.api-tokens.store', $user) }}" class="form-grid">
            @csrf
            <label>Nom de la clé API
                <input name="name" required maxlength="255" value="{{ old('name') }}" placeholder="Ex. intégration catalogue">
            </label>
            <fieldset>
                <legend>Permissions accordées à cette clé</legend>
                @forelse($apiTokenAbilities as $ability)
                    <label>
                        <input type="checkbox" name="abilities[]" value="{{ $ability }}" @checked(in_array($ability, (array) old('abilities', []), true))>
                        {{ $ability }}
                    </label>
                @empty
                    <p class="muted">Aucune permission active n’est attribuée à cet utilisateur.</p>
                @endforelse
            </fieldset>
            <p class="muted">Jetons actifs : {{ $activeApiTokenCount }} / {{ $apiTokenLimit }}. Expiration : 90 jours.</p>
            <button class="button" type="submit">Créer une clé API pour cet utilisateur</button>
        </form>
    @elseif($user->isAuthorized())
        <p class="muted">La limite de clés API actives de cet utilisateur est atteinte ({{ $apiTokenLimit }}).</p>
    @else
        <p class="muted">Une clé API ne peut être créée que pour un compte actif et vérifié.</p>
    @endif

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
                                && $token->expires_at !== null
                                && $token->expires_at->isFuture();
                        @endphp
                        <tr>
                            <td>{{ $token->name }}</td>
                            <td>{{ $token->created_at?->format('d/m/Y H:i') ?? '—' }}</td>
                            <td>{{ $token->last_used_at?->format('d/m/Y H:i') ?? 'Jamais' }}</td>
                            <td>{{ $token->expires_at?->format('d/m/Y H:i') ?? '—' }}</td>
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
