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
                            @if($canManageStock)
                                <th>Opérations</th>
                            @endif
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
                                @if($canManageStock)
                                    <td>
                                        <details>
                                            <summary>Entrée de stock</summary>
                                            <form method="POST"
                                                  action="{{ route('backoffice.inventory.stocks.receive', ['cooperativeId' => $stock['cooperative_id']]) }}"
                                                  data-inventory-stock-operation>
                                                @csrf
                                                <input type="hidden" name="stockable_type" value="{{ $stock['stockable_type'] }}">
                                                <input type="hidden" name="stockable_id" value="{{ $stock['stockable_id'] }}">
                                                <input type="hidden" name="idempotency_key" value="{{ \Illuminate\Support\Str::uuid() }}">
                                                <label>Quantité reçue</label>
                                                <input name="quantity" type="text" inputmode="decimal" required placeholder="Ex. 2.500">
                                                <label>Nature de l’entrée</label>
                                                <select name="movement_type">
                                                    <option value="purchase_receipt">Réception</option>
                                                    <option value="production_entry">Production</option>
                                                    <option value="manual_adjustment_in">Correction positive</option>
                                                </select>
                                                <label>Motif obligatoire</label>
                                                <input name="reason" type="text" maxlength="500" required>
                                                <button class="button button-small" type="submit">Enregistrer l’entrée</button>
                                            </form>
                                        </details>
                                        @if($canManageThreshold)
                                            <details>
                                                <summary>Seuil d’alerte</summary>
                                                <form method="POST"
                                                      action="{{ route('backoffice.inventory.stocks.threshold.update', ['stockId' => $stock['id']]) }}"
                                                      data-inventory-stock-operation>
                                                    @csrf
                                                    <input type="hidden" name="idempotency_key" value="{{ \Illuminate\Support\Str::uuid() }}">
                                                    <label>Seuil de stock bas</label>
                                                    <input name="threshold" type="text" inputmode="decimal" required placeholder="Ex. 5.000">
                                                    <label>Motif obligatoire</label>
                                                    <input name="reason" type="text" maxlength="500" required>
                                                    <button class="button button-small" type="submit">Enregistrer le seuil</button>
                                                </form>
                                            </details>
                                        @endif
                                        <details>
                                            <summary>Ajustement manuel</summary>
                                            <form method="POST"
                                                  action="{{ route('backoffice.inventory.stocks.adjust', ['cooperativeId' => $stock['cooperative_id']]) }}"
                                                  data-inventory-stock-operation>
                                                @csrf
                                                <input type="hidden" name="stockable_type" value="{{ $stock['stockable_type'] }}">
                                                <input type="hidden" name="stockable_id" value="{{ $stock['stockable_id'] }}">
                                                <input type="hidden" name="idempotency_key" value="{{ \Illuminate\Support\Str::uuid() }}">
                                                <label>Quantité signée</label>
                                                <input name="signed_quantity" type="text" inputmode="decimal" required placeholder="Ex. 1.000 ou -0.500">
                                                <label>Motif obligatoire</label>
                                                <input name="reason" type="text" maxlength="500" required>
                                                <button class="button button-small" type="submit">Appliquer l’ajustement</button>
                                            </form>
                                        </details>
                                    </td>
                                @endif
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

@if($canCreateCount || $canManageStock || $canManageThreshold)
    @push('scripts')
    <script>
    document.querySelectorAll('[data-inventory-stock-operation]').forEach((form) => {
        form.addEventListener('submit', async (event) => {
            event.preventDefault();
            const button = form.querySelector('button[type="submit"]');
            if (button) button.disabled = true;

            try {
                const payload = Object.fromEntries(new FormData(form).entries());
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
                    const message = result.errors
                        ? Object.values(result.errors).flat().join(' ')
                        : (result.message || 'L’opération de stock a échoué.');
                    throw new Error(message);
                }

                window.location.reload();
            } catch (error) {
                window.alert(error.message || 'Une erreur est survenue.');
                if (button) button.disabled = false;
            }
        });
    });
    </script>
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
    

@endpush
@endif
