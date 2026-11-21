@extends('layouts.admin')

@section('title', 'Inventaire')

@section('content')
    <div class="page-header">
        <div>
            <h1>Inventaire</h1>
            <p class="muted">État des stocks et suivi des inventaires physiques dans votre périmètre.</p>
        </div>
        <div class="actions">
            @foreach($cooperativeIds as $cooperativeId)
                <a class="button button-secondary button-small"
                   href="{{ route('inventory.reports.pdf', ['cooperativeId' => $cooperativeId]) }}">
                    Rapport PDF · {{ substr($cooperativeId, 0, 8) }}
                </a>
            @endforeach
        </div>
    </div>

    <section class="card">
        <div class="page-header">
            <div>
                <h2>État des stocks</h2>
                <p class="muted">Seuls les états « En stock » et « Rupture de stock » sont affichés. Les quantités internes ne sont pas exposées ici.</p>
            </div>
        </div>

        @if($stocks === [])
            <p class="empty">Aucun stock n'est encore enregistré dans votre périmètre.</p>
        @else
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Coopérative</th>
                            <th>Type</th>
                            <th>Produit / variante</th>
                            <th>Unité</th>
                            <th>Disponibilité</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($stocks as $stock)
                            <tr>
                                <td><code>{{ $stock['cooperative_id'] }}</code></td>
                                <td>{{ $stock['stockable_type'] === 'variant' ? 'Variante' : 'Produit' }}</td>
                                <td><code>{{ $stock['stockable_id'] }}</code></td>
                                <td>{{ $stock['unit_code'] }}</td>
                                <td>
                                    <span class="badge {{ $stock['status'] === 'En stock' ? '' : 'badge-inactive' }}">
                                        {{ $stock['status'] }}
                                    </span>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>

    @if($canCreateCount)
        <section class="card">
            <h2>Créer une session de comptage</h2>
            <p class="muted">Sélectionnez les produits ou variantes à compter. Les éléments sélectionnés seront temporairement verrouillés.</p>
            @foreach($stocksByCooperative as $cooperativeId => $cooperativeStocks)
                <form method="POST"
                      action="{{ route('backoffice.inventory.count-sessions.create', ['cooperativeId' => $cooperativeId]) }}"
                      data-inventory-create-count
                      data-workspace-url="{{ route('backoffice.inventory.count-sessions.workspace', ['sessionId' => '__SESSION__']) }}">
                    @csrf
                    <input type="hidden" name="idempotency_key" value="{{ \Illuminate\Support\Str::uuid() }}">
                    <h3>Coopérative <code>{{ $cooperativeId }}</code></h3>
                    <div class="table-wrap">
                        <table>
                            <thead><tr><th>Sélection</th><th>Type</th><th>Produit / variante</th><th>Unité</th><th>État</th></tr></thead>
                            <tbody>
                                @foreach($cooperativeStocks as $stock)
                                    <tr>
                                        <td><input type="checkbox" name="stock_ids[]" value="{{ $stock['id'] }}"></td>
                                        <td>{{ $stock['stockable_type'] === 'variant' ? 'Variante' : 'Produit' }}</td>
                                        <td><code>{{ $stock['stockable_id'] }}</code></td>
                                        <td>{{ $stock['unit_code'] }}</td>
                                        <td>{{ $stock['status'] }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <button class="button" type="submit">Créer et ouvrir la session</button>
                </form>
            @endforeach
        </section>
    @endif

    @if($canCreateCount)
        <section class="card">
            <h2>Créer une session d’inventaire</h2>
            @foreach($cooperativeIds as $cooperativeId)
                @php($cooperativeStocks = $stocksByCooperative->get($cooperativeId, collect()))
                <form method="POST"
                      action="{{ route('backoffice.inventory.count-sessions.create', ['cooperativeId' => $cooperativeId]) }}"
                      data-count-create-form
                      data-workspace-url="{{ route('backoffice.inventory.count-sessions.workspace', ['sessionId' => '__SESSION_ID__']) }}">
                    @csrf
                    <input type="hidden" name="idempotency_key" value="{{ (string) \Illuminate\Support\Str::ulid() }}">
                    <h3>Coopérative <code>{{ $cooperativeId }}</code></h3>
                    @forelse($cooperativeStocks as $stock)
                        <label class="inventory-stock-choice">
                            <input type="checkbox" name="stock_ids[]" value="{{ $stock['id'] }}">
                            <span>{{ $stock['stockable_type'] === 'variant' ? 'Variante' : 'Produit' }}
                                <code>{{ $stock['stockable_id'] }}</code> · {{ $stock['unit_code'] }} · {{ $stock['status'] }}</span>
                        </label>
                    @empty
                        <p class="muted">Aucun stock à inventorier.</p>
                    @endforelse
                    @if($cooperativeStocks->isNotEmpty())
                        <button class="button button-secondary button-small" type="submit">Créer la session</button>
                    @endif
                </form>
            @endforeach
        </section>
    @endif

    <section class="card">
        <h2>Inventaires physiques</h2>
        @if($countSessions === [])
            <p class="empty">Aucune session d'inventaire physique n'est enregistrée dans votre périmètre.</p>
        @else
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Coopérative</th>
                            <th>Session</th>
                            <th>Statut</th>
                            <th>Éléments comptés</th>
                            <th>Éléments totaux</th>
                            <th>Démarrée le</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($countSessions as $session)
                            <tr>
                                <td><code>{{ $session['cooperative_id'] }}</code></td>
                                <td><code>{{ $session['id'] }}</code></td>
                                <td><span class="badge">{{ str($session['status'])->replace('_', ' ')->title() }}</span></td>
                                <td>{{ $session['counted_items_count'] }}</td>
                                <td>{{ $session['items_count'] }}</td>
                                <td>{{ $session['started_at'] ? \Illuminate\Support\Carbon::parse($session['started_at'])->format('d/m/Y H:i') : '—' }}</td>
                                <td>
                                    <a class="button button-secondary button-small"
                                       href="{{ route('backoffice.inventory.count-sessions.workspace', ['sessionId' => $session['id']]) }}">
                                        Ouvrir la session
                                    </a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>
@endsection

@if($canCreateCount)
    @push('scripts')
    <script>
    document.querySelectorAll('[data-inventory-create-count]').forEach((form) => {
        form.addEventListener('submit', async (event) => {
            event.preventDefault();
            const button = form.querySelector('button[type="submit"]');
            button.disabled = true;

            try {
                const formData = new FormData(form);
                const payload = {
                    idempotency_key: formData.get('idempotency_key'),
                    stock_ids: formData.getAll('stock_ids[]'),
                };
                if (payload.stock_ids.length === 0) {
                    throw new Error('Sélectionnez au moins un élément à compter.');
                }

                const response = await fetch(form.action, {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    },
                    body: JSON.stringify(payload),
                });
                const result = await response.json();

                if (!response.ok) {
                    const message = result.errors
                        ? Object.values(result.errors).flat().join(' ')
                        : (result.message || 'La création de la session a échoué.');
                    throw new Error(message);
                }

                window.location.href = form.dataset.workspaceUrl.replace('__SESSION__', result.session_id);
            } catch (error) {
                window.alert(error.message || 'Une erreur est survenue.');
                button.disabled = false;
            }
        });
    });
    </script>
    <script>
document.querySelectorAll('[data-count-create-form]').forEach((form) => {
    form.addEventListener('submit', async (event) => {
        event.preventDefault();
        const selected = form.querySelectorAll('input[name="stock_ids[]"]:checked');
        if (selected.length === 0) {
            alert('Sélectionnez au moins un produit ou une variante.');
            return;
        }

        const button = form.querySelector('button[type="submit"]');
        if (button) button.disabled = true;

        try {
            const payload = Object.fromEntries(new FormData(form).entries());
            payload.stock_ids = Array.from(selected, (input) => input.value);
            delete payload._token;
            const response = await fetch(form.action, {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    'Idempotency-Key': payload.idempotency_key,
                },
                body: JSON.stringify(payload),
            });
            const result = await response.json();
            if (!response.ok) {
                throw new Error(result.message || Object.values(result.errors || {}).flat().join(' ') || 'Création impossible.');
            }

            window.location.href = form.dataset.workspaceUrl.replace('__SESSION_ID__', result.session_id);
        } catch (error) {
            alert(error.message || 'Une erreur est survenue.');
            if (button) button.disabled = false;
        }
    });
});
</script>

@endpush
@endif
