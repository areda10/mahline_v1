@extends(auth()->user()->hasRole('super_admin_root') ? 'layouts.root' : 'layouts.admin')

@section('title', 'Sécurité — appareils connectés')

@section('content')
<div class="page-header">
    <div>
        <h1>Mes appareils connectés</h1>
        <p class="muted">Consultez les appareils connectés à votre compte et révoquez individuellement ceux que vous ne reconnaissez plus.</p>
    </div>
</div>
<section class="card" aria-labelledby="sessions-heading">
    <h2 id="sessions-heading">Sessions actives</h2>
    @if($sessions->isEmpty())
        <div class="empty">Aucun appareil connecté.</div>
    @else
        <div class="table-wrap">
            <table>
                <thead><tr><th>Appareil</th><th>Navigateur</th><th>Adresse IP</th><th>Connexion</th><th>Dernière activité</th><th>État</th><th>Action</th></tr></thead>
                <tbody>
                    @foreach($sessions as $session)
                        <tr>
                            <td>{{ $session->device ?: 'Appareil inconnu' }}</td>
                            <td>{{ $session->browser ?: '—' }}</td>
                            <td>{{ $session->ip_address ?: '—' }}</td>
                            <td>{{ $session->authenticated_at?->format('d/m/Y H:i') ?? '—' }}</td>
                            <td>{{ $session->last_activity_at?->format('d/m/Y H:i') ?? '—' }}</td>
                            <td>{{ $session->is_current ? 'Cet appareil' : 'Connecté' }}</td>
                            <td>
                                <form method="POST" action="{{ route(auth()->user()->hasRole('super_admin_root') ? 'root.backoffice.security.sessions.destroy' : 'backoffice.security.sessions.destroy', $session) }}" onsubmit="return confirm('Révoquer cette session ?');">
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
    @endif
</section>
@endsection
