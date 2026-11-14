@php
    $user = auth()->user();
    $isCustomer = $user->hasRole('customer');
    $isRoot = $user->hasRole('super_admin_root');
    $baseRoute = $isCustomer
        ? 'customer.security.devices'
        : ($isRoot ? 'root.backoffice.security.devices' : 'backoffice.security.devices');
    $layout = $isCustomer ? 'layouts.customer' : ($isRoot ? 'layouts.root' : 'layouts.admin');
@endphp

@extends($layout)

@section('title', 'Sécurité — appareils de confiance')

@section('content')
<div class="page-header">
    <div>
        <h1>Mes appareils</h1>
        <p class="muted">Un nouvel appareil peut utiliser votre compte immédiatement. Il devient automatiquement de confiance après 24 heures, sauf révocation.</p>
    </div>
    <span>{{ $trustedCount }} / 5 appareils de confiance</span>
</div>

@if(session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
@endif
@if($errors->has('device'))
    <div class="alert alert-danger">{{ $errors->first('device') }}</div>
@endif

@if($securityNotifications->isNotEmpty())
    <section class="card" aria-labelledby="device-notifications-heading">
        <h2 id="device-notifications-heading">Notifications de sécurité</h2>
        @foreach($securityNotifications as $notification)
            <p>
                Appareil révoqué : {{ $notification->data['device'] ?? 'Appareil inconnu' }}
                — {{ isset($notification->data['revoked_at']) ? \Illuminate\Support\Carbon::parse($notification->data['revoked_at'])->format('d/m/Y H:i') : '—' }}
            </p>
        @endforeach
    </section>
@endif

<section class="card">
    <h2>Appareils enregistrés</h2>
    @if($devices->isEmpty())
        <p class="empty">Aucun appareil enregistré pour le moment.</p>
    @else
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Appareil</th>
                        <th>Navigateur</th>
                        <th>Dernière activité</th>
                        <th>Statut</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($devices as $device)
                        @php
                            $isCurrent = (string) $currentDeviceId === (string) $device->getKey();
                            $status = $device->status->value;
                        @endphp
                        <tr>
                            <td>
                                {{ $device->device ?: 'Appareil inconnu' }}
                                @if($isCurrent) <strong>(cet appareil)</strong> @endif
                            </td>
                            <td>{{ $device->browser ?: '—' }}</td>
                            <td>{{ $device->last_seen_at?->format('d/m/Y H:i') ?? '—' }}</td>
                            <td>
                                @if($status === 'trusted')
                                    De confiance
                                @elseif($status === 'pending')
                                    En attente jusqu’au {{ $device->pending_until?->format('d/m/Y H:i') ?? '—' }}
                                @else
                                    Non fiable / révoqué
                                @endif
                            </td>
                            <td>
                                @if($status !== 'non_trusted')
                                    <form method="POST"
                                          action="{{ route($baseRoute.'.destroy', $device) }}"
                                          onsubmit="return confirm('Révoquer cet appareil et couper toutes ses sessions Web ?');">
                                        @csrf
                                        @method('DELETE')
                                        <button class="button button-danger button-small" type="submit">Révoquer</button>
                                    </form>
                                @endif

                                @if($isCurrent && $status === 'pending' && $trustedCount >= 5)
                                    <p class="muted">Pour faire confiance à cet appareil, choisissez un appareil de confiance à remplacer :</p>
                                    @foreach($devices->filter(fn ($knownDevice) => $knownDevice->isTrusted()) as $trustedDevice)
                                        <form method="POST"
                                              action="{{ route($baseRoute.'.replace', $trustedDevice) }}"
                                              onsubmit="return confirm('Révoquer cet appareil et faire confiance à celui-ci ?');">
                                            @csrf
                                            <button class="button button-secondary button-small" type="submit">
                                                Remplacer {{ $trustedDevice->device ?: 'appareil' }}
                                            </button>
                                        </form>
                                    @endforeach
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
