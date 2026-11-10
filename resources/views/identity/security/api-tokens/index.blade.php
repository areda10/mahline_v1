@extends(auth()->user()->hasRole('super_admin_root') ? 'layouts.root' : 'layouts.admin')

@section('title', 'Sécurité — clés API')

@section('content')
<div class="page-header">
    <div>
        <h1>Mes clés API</h1>
        <p class="muted">Créez et gérez vos propres clés API. Une clé secrète n’est affichée qu’une seule fois à sa création. La révocation des clés API ne déconnecte pas vos sessions Web.</p>
    </div>
</div>

@if(session('new_api_token'))
    <section class="card" aria-labelledby="new-token-heading">
        <h2 id="new-token-heading">Nouvelle clé API — à copier maintenant</h2>
        <p class="muted">Cette valeur ne sera plus affichée après avoir quitté ou actualisé cette page.</p>
        <textarea readonly rows="3" class="form-control" onclick="this.select()">{{ session('new_api_token') }}</textarea>
    </section>
@endif

@if(session('success'))
    <div class="alert alert-success" role="status">{{ session('success') }}</div>
@endif

@if($errors->any())
    <div class="alert alert-danger" role="alert">
        @foreach($errors->all() as $error)
            <p>{{ $error }}</p>
        @endforeach
    </div>
@endif

<section class="card" aria-labelledby="create-token-heading">
    <h2 id="create-token-heading">Créer une clé API</h2>
    <form method="POST" action="{{ route(auth()->user()->hasRole('super_admin_root') ? 'root.backoffice.security.api-tokens.store' : 'backoffice.security.api-tokens.store') }}">
        @csrf
        <label for="token-name">Nom de la clé</label>
        <input id="token-name" name="name" type="text" maxlength="255" required value="{{ old('name') }}" autocomplete="off">
        <button class="button" type="submit">Créer la clé API</button>
    </form>
</section>

<section class="card" aria-labelledby="tokens-heading">
    <div class="page-header">
        <h2 id="tokens-heading">Historique des clés API</h2>
        @if($tokens->contains(fn ($token) => $token->revoked_at === null))
            <form method="POST" action="{{ route(auth()->user()->hasRole('super_admin_root') ? 'root.backoffice.security.api-tokens.destroy-all' : 'backoffice.security.api-tokens.destroy-all') }}"
                  onsubmit="return confirm('Révoquer toutes vos clés API actives ? Vos sessions Web resteront connectées.');">
                @csrf
                @method('DELETE')
                <button class="button button-danger" type="submit">Révoquer toutes mes clés API</button>
            </form>
        @endif
    </div>

    @if($tokens->isEmpty())
        <div class="empty">Aucune clé API créée.</div>
    @else
        <div class="table-wrap">
            <table>
                <thead>
                    <tr><th>Nom</th><th>Créée le</th><th>Dernière utilisation</th><th>Expiration</th><th>État</th><th>Action</th></tr>
                </thead>
                <tbody>
                    @foreach($tokens as $token)
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
                                          action="{{ route(auth()->user()->hasRole('super_admin_root') ? 'root.backoffice.security.api-tokens.destroy' : 'backoffice.security.api-tokens.destroy', $token) }}"
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
@endsection
