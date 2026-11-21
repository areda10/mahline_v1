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
                                            <summary>Seuil de stock faible</summary>
                                            <form method="POST"
                                                  action="{{ route('backoffice.inventory.stocks.threshold.update', ['stockId' => $stock['id']]) }}"
                                                  data-inventory-stock-operation>
                                                @csrf
                                                <input type="hidden" name="idempotency_key" value="{{ (string) \Illuminate\Support\Str::ulid() }}">
                                                <label>Seuil en {{ $stock['unit_code'] }}</label>
                                                <input name="threshold" type="text" inputmode="decimal" required placeholder="Ex. 5.000">
                                                <label>Motif obligatoire</label>
                                                <input name="reason" type="text" maxlength="500" required>
                                                <button class="button button-small" type="submit">Enregistrer le seuil</button>
                                            </form>
                                        </details>
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

    @if($canInitializeStock)
        <section class="card">
            <h2>Initialiser un stock</h2>
            <p class="muted">Créez le suivi de stock d’un produit simple ou d’une variante. L’unité doit correspondre à l’unité canonique définie dans Products.</p>
            <form method="POST"
                  action="{{ route('backoffice.inventory.stocks.initialize', ['cooperativeId' => '__COOP__']) }}"
                  data-inventory-initialize-stock
                  data-endpoint-template="{{ route('backoffice.inventory.stocks.initialize', ['cooperativeId' => '__COOP__']) }}">
                @csrf
                <div class="field">
                    <label>Identifiant ULID de la coopérative</label>
                    <input name="cooperative_id" type="text" minlength="26" maxlength="26" value="{{ $defaultCooperativeId }}" required>
                </div>
                <div class="field">
                    <label>Type de stockable</label>
                    <select name="stockable_type" required>
                        <option value="product">Produit simple</option>
                        <option value="variant">Variante</option>
                    </select>
                </div>
                <div class="field">
                    <label>Identifiant ULID du produit ou de la variante</label>
                    <input name="stockable_id" type="text" minlength="26" maxlength="26" required>
                </div>
                <div class="field">
                    <label>Unité canonique</label>
                    <select name="unit_code" required>
                        <option value="kg">kg</option>
                        <option value="g">g</option>
                        <option value="l">l</option>
                        <option value="ml">ml</option>
                        <option value="u">unité</option>
                    </select>
                </div>
                <div class="field">
                    <label>Quantité initiale</label>
                    <input name="quantity" type="text" inputmode="decimal" required placeholder="Ex. 0.000">
                </div>
                <div class="field">
                    <label>Motif obligatoire</label>
                    <input name="reason" type="text" maxlength="500" required>
                </div>
                <input type="hidden" name="idempotency_key" value="{{ (string) \Illuminate\Support\Str::ulid() }}">
                <button class="button" type="submit">Initialiser le stock</button>
            </form>
        </section>
    @endif

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

    @if($canManageQuarantine)
        <section class="card">
            <h2>Quarantaine et retours</h2>
            @foreach($cooperativeIds as $cooperativeId)
                <details>
                    <summary>Réceptionner un retour · Coopérative <code>{{ $cooperativeId }}</code></summary>
                    <form method="POST"
                          action="{{ route('backoffice.inventory.quarantines.receive', ['cooperativeId' => $cooperativeId]) }}"
                          data-inventory-quarantine-operation>
                        @csrf
                        <input type="hidden" name="idempotency_key" value="{{ (string) \Illuminate\Support\Str::ulid() }}">
                        <label>Type</label>
                        <select name="stockable_type"><option value="product">Produit</option><option value="variant">Variante</option></select>
                        <label>Identifiant produit / variante</label>
                        <input name="stockable_id" type="text" minlength="26" maxlength="26" required>
                        <label>Quantité reçue</label>
                        <input name="quantity" type="text" inputmode="decimal" required>
                        <label>Type de source</label>
                        <input name="source_type" type="text" maxlength="60" value="customer_return" required>
                        <label>Identifiant ULID de la source</label>
                        <input name="source_id" type="text" minlength="26" maxlength="26" required>
                        <label>Motif de mise en quarantaine</label>
                        <input name="reason" type="text" maxlength="500" required>
                        <button class="button button-small" type="submit">Réceptionner en quarantaine</button>
                    </form>
                </details>
            @endforeach

            @if($quarantines === [])
                <p class="empty">Aucun lot en quarantaine n’est enregistré dans votre périmètre.</p>
            @else
                <div class="table-wrap">
                    <table>
                        <thead>
                            <tr><th>Produit / variante</th><th>Reçu</th><th>Restant</th><th>Autorisé</th><th>État</th><th>Inspection</th><th>Motif</th><th>Actions</th></tr>
                        </thead>
                        <tbody>
                            @foreach($quarantines as $quarantine)
                                <tr>
                                    <td>
                                        {{ $quarantine['stockable_type'] === 'variant' ? 'Variante' : 'Produit' }}
                                        <div><code>{{ $quarantine['stockable_id'] }}</code></div>
                                    </td>
                                    <td>{{ $quarantine['quantity_received'] }} {{ $quarantine['unit_code'] }}</td>
                                    <td>{{ $quarantine['quantity_remaining'] }} {{ $quarantine['unit_code'] }}</td>
                                    <td>{{ $quarantine['quantity_authorized'] }} {{ $quarantine['unit_code'] }}</td>
                                    <td>{{ str($quarantine['status'])->replace('_', ' ')->title() }}</td>
                                    <td>{{ str($quarantine['inspection_result'])->replace('_', ' ')->title() }}</td>
                                    <td>{{ $quarantine['reason'] }}</td>
                                    <td>
                                        @if($quarantine['inspection_result'] === 'pending')
                                            <details>
                                                <summary>Inspecter</summary>
                                                <form method="POST"
                                                      action="{{ route('backoffice.inventory.quarantines.inspect', ['quarantineId' => $quarantine['id']]) }}"
                                                      data-inventory-quarantine-operation>
                                                    @csrf
                                                    <input type="hidden" name="idempotency_key" value="{{ (string) \Illuminate\Support\Str::ulid() }}">
                                                    <label>Résultat</label>
                                                    <select name="result">
                                                        <option value="compliant">Conforme · réintégrer au stock normal</option>
                                                        <option value="non_compliant">Non conforme · rester en quarantaine</option>
                                                    </select>
                                                    <label>Motif obligatoire</label>
                                                    <input name="reason" type="text" maxlength="500" required>
                                                    <button class="button button-small" type="submit">Enregistrer l’inspection</button>
                                                </form>
                                            </details>
                                        @endif
                                        @if($quarantine['inspection_result'] === 'non_compliant')
                                            <details>
                                                <summary>Autoriser une vente conditionnelle</summary>
                                                <form method="POST"
                                                      action="{{ route('backoffice.inventory.quarantines.authorize-sale', ['quarantineId' => $quarantine['id']]) }}"
                                                      data-inventory-quarantine-operation>
                                                    @csrf
                                                    <input type="hidden" name="idempotency_key" value="{{ (string) \Illuminate\Support\Str::ulid() }}">
                                                    <label>Quantité à autoriser</label>
                                                    <input name="quantity" type="text" inputmode="decimal" required>
                                                    <label>Motif et responsabilité</label>
                                                    <input name="reason" type="text" maxlength="500" required>
                                                    <button class="button button-small" type="submit">Autoriser la vente</button>
                                                </form>
                                            </details>
                                        @endif
                                        @foreach($quarantine['authorizations'] as $authorization)
                                            @if($authorization['status'] === 'active')
                                                <details>
                                                    <summary>Révoquer l’autorisation {{ substr($authorization['id'], 0, 8) }}</summary>
                                                    <form method="POST"
                                                          action="{{ route('backoffice.inventory.quarantine-authorizations.revoke', ['authorizationId' => $authorization['id']]) }}"
                                                          data-inventory-quarantine-operation>
                                                        @csrf
                                                        <input type="hidden" name="idempotency_key" value="{{ (string) \Illuminate\Support\Str::ulid() }}">
                                                        <label>Motif obligatoire</label>
                                                        <input name="reason" type="text" maxlength="500" required>
                                                        <button class="button button-danger button-small" type="submit">Révoquer</button>
                                                    </form>
                                                </details>
                                            @endif
                                        @endforeach
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
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

@if($canCreateCount || $canManageStock || $canManageThreshold || $canInitializeStock)
    @push('scripts')
    <script>
    document.querySelectorAll('[data-inventory-initialize-stock]').forEach((form) => {
        form.addEventListener('submit', async (event) => {
            event.preventDefault();
            const button = form.querySelector('button[type="submit"]');
            button.disabled = true;

            try {
                const payload = Object.fromEntries(new FormData(form).entries());
                delete payload._token;
                const cooperativeId = payload.cooperative_id;
                delete payload.cooperative_id;
                const url = form.dataset.endpointTemplate.replace('__COOP__', encodeURIComponent(cooperativeId));
                const response = await fetch(url, {
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
                        : (result.message || 'L’initialisation du stock a échoué.');
                    throw new Error(message);
                }

                window.location.reload();
            } catch (error) {
                window.alert(error.message || 'Une erreur est survenue.');
                button.disabled = false;
            }
        });
    });
    </script>
    <script>
    document.querySelectorAll('[data-inventory-quarantine-operation]').forEach((form) => {
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
                        : (result.message || 'L’opération de quarantaine a échoué.');
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
