<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Rapport Inventory MAHLINE</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 9px; color: #202124; }
        h1 { font-size: 18px; margin-bottom: 4px; }
        h2 { font-size: 13px; margin-top: 20px; }
        .meta { color: #5f6368; margin-bottom: 18px; }
        table { width: 100%; border-collapse: collapse; margin-top: 8px; }
        th, td { border: 1px solid #d7dce1; padding: 5px; text-align: left; }
        th { background: #f1f3f4; }
        .muted { color: #6b7280; }
    </style>
</head>
<body>
    <h1>Rapport de stock — MAHLINE</h1>
    <div class="meta">
        Coopérative : {{ $cooperativeId }} · Généré le {{ $generatedAt->format('d/m/Y H:i') }}
        @if($simplified) · Rapport simplifié @endif
    </div>

    <h2>État des stocks</h2>
    <table>
        <thead>
            <tr>
                <th>Type</th><th>Identifiant produit/variante</th><th>Unité</th><th>Disponibilité</th>
                @unless($simplified)
                    <th>Physique</th><th>Réservé</th><th>Quarantaine</th><th>Quarantaine autorisée</th><th>Disponible</th><th>Seuil bas</th>
                @endunless
            </tr>
        </thead>
        <tbody>
            @forelse($stocks as $stock)
                <tr>
                    <td>{{ $stock['stockable_type'] }}</td>
                    <td>{{ $stock['stockable_id'] }}</td>
                    <td>{{ $stock['unit_code'] }}</td>
                    <td>{{ $stock['status'] }}</td>
                    @unless($simplified)
                        <td>{{ $stock['quantity_on_hand'] }}</td>
                        <td>{{ $stock['quantity_reserved'] }}</td>
                        <td>{{ $stock['quantity_quarantined'] }}</td>
                        <td>{{ $stock['quantity_quarantine_authorized'] }}</td>
                        <td>{{ $stock['available_quantity'] }}</td>
                        <td>{{ $stock['low_stock_threshold'] }}</td>
                    @endunless
                </tr>
            @empty
                <tr><td colspan="{{ $simplified ? 4 : 10 }}">Aucun stock enregistré.</td></tr>
            @endforelse
        </tbody>
    </table>

    @unless($simplified)
        <h2>Historique des mouvements (5000 maximum)</h2>
        <table>
            <thead><tr><th>Date</th><th>Type</th><th>Variation</th><th>Motif</th><th>Référence</th><th>Auteur</th></tr></thead>
            <tbody>
                @forelse($movements as $movement)
                    <tr>
                        <td>{{ $movement->occurred_at?->format('d/m/Y H:i') }}</td>
                        <td>{{ $movement->movement_type }}</td>
                        <td>{{ $movement->quantity_delta }} {{ $movement->unit_code }}</td>
                        <td>{{ $movement->reason }}</td>
                        <td>{{ $movement->reference_type }} {{ $movement->reference_id }}</td>
                        <td>{{ $movement->actor_id }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6">Aucun mouvement.</td></tr>
                @endforelse
            </tbody>
        </table>

        <h2>Alertes de stock</h2>
        <table>
            <thead><tr><th>Type</th><th>Déclenchée le</th><th>Disponibilité au déclenchement</th><th>Seuil</th><th>Clôturée le</th></tr></thead>
            <tbody>
                @forelse($alerts as $alert)
                    <tr><td>{{ $alert->alert_type }}</td><td>{{ $alert->triggered_at?->format('d/m/Y H:i') }}</td><td>{{ $alert->available_quantity_at_trigger }}</td><td>{{ $alert->threshold_at_trigger ?? '—' }}</td><td>{{ $alert->cleared_at?->format('d/m/Y H:i') ?? 'Active' }}</td></tr>
                @empty
                    <tr><td colspan="5">Aucune alerte.</td></tr>
                @endforelse
            </tbody>
        </table>

        <h2>Inventaires physiques</h2>
        <table>
            <thead><tr><th>Session</th><th>Début</th><th>Statut</th><th>Stock</th><th>Théorique</th><th>Compté</th><th>Écart</th><th>Motif</th></tr></thead>
            <tbody>
                @forelse($countSessions as $session)
                    @foreach($session->items as $item)
                        <tr><td>{{ $session->getKey() }}</td><td>{{ $session->started_at?->format('d/m/Y H:i') }}</td><td>{{ $session->status }}</td><td>{{ $item->stock_id }}</td><td>{{ $item->expected_quantity }}</td><td>{{ $item->counted_quantity ?? '—' }}</td><td>{{ $item->difference_quantity ?? '—' }}</td><td>{{ $item->difference_reason }}</td></tr>
                    @endforeach
                @empty
                    <tr><td colspan="8">Aucune session d’inventaire.</td></tr>
                @endforelse
            </tbody>
        </table>

        <h2>Quarantaines et inspections</h2>
        <table>
            <thead><tr><th>Reçue le</th><th>Stock</th><th>Quantité reçue</th><th>Restante</th><th>Autorisée</th><th>Statut</th><th>Inspection</th><th>Motif</th></tr></thead>
            <tbody>
                @forelse($quarantines as $quarantine)
                    <tr><td>{{ $quarantine->received_at?->format('d/m/Y H:i') }}</td><td>{{ $quarantine->stock_id }}</td><td>{{ $quarantine->quantity_received }}</td><td>{{ $quarantine->quantity_remaining }}</td><td>{{ $quarantine->quantity_authorized }}</td><td>{{ $quarantine->status }}</td><td>{{ $quarantine->inspection_result }}</td><td>{{ $quarantine->inspection_reason ?? $quarantine->reason }}</td></tr>
                @empty
                    <tr><td colspan="8">Aucune quarantaine.</td></tr>
                @endforelse
            </tbody>
        </table>
    @endunless
    <p class="muted">Document confidentiel — MAHLINE</p>
</body>
</html>
