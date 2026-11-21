@extends('layouts.admin')

@section('title', 'Session d’inventaire')

@section('content')
    <div class="page-header">
        <div>
            <h1>Session d’inventaire</h1>
            <p class="muted">
                Coopérative : <code>{{ $session['cooperative_id'] }}</code> ·
                Session : <code>{{ $session['id'] }}</code>
            </p>
        </div>
        <a class="button button-secondary" href="{{ route('backoffice.inventory.index') }}">Retour à l’inventaire</a>
    </div>

    <section class="card">
        <div class="actions">
            <span class="badge">{{ str($session['status'])->replace('_', ' ')->title() }}</span>
            <span class="muted">Démarrée : {{ $session['started_at'] ? \Illuminate\Support\Carbon::parse($session['started_at'])->format('d/m/Y H:i') : '—' }}</span>
            @if($session['completed_at'])
                <span class="muted">Terminée : {{ \Illuminate\Support\Carbon::parse($session['completed_at'])->format('d/m/Y H:i') }}</span>
            @endif
        </div>
    </section>

    <div class="alert alert-error" role="alert" data-inventory-error hidden></div>

    <section class="card">
        <h2>Éléments à compter</h2>
        @if($session['items'] === [])
            <p class="empty">Cette session ne contient aucun élément.</p>
        @else
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Produit / variante</th>
                            <th>Unité</th>
                            @if($session['can_manage'])
                                <th>Théorique</th>
                            @endif
                            <th>Compté</th>
                            @if($session['can_manage'])
                                <th>Écart</th>
                                <th>Motif de l’écart</th>
                            @endif
                            <th>État</th>
                            @if(in_array($session['status'], ['active', 'partially_validated'], true))
                                <th>Action</th>
                            @endif
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($session['items'] as $item)
                            <tr>
                                <td>
                                    <strong>{{ $item['stockable_type'] === 'variant' ? 'Variante' : 'Produit' }}</strong>
                                    <div><code>{{ $item['stockable_id'] }}</code></div>
                                </td>
                                <td>{{ $item['unit_code'] }}</td>
                                @if($session['can_manage'])
                                    <td>{{ $item['expected_quantity'] }}</td>
                                @endif
                                <td>{{ $item['counted_quantity'] ?? '—' }}</td>
                                @if($session['can_manage'])
                                    <td>{{ $item['difference_quantity'] ?? '—' }}</td>
                                    <td>{{ $item['difference_reason'] ?? '—' }}</td>
                                @endif
                                <td><span class="badge">{{ str($item['status'])->replace('_', ' ')->title() }}</span></td>
                                @if(in_array($session['status'], ['active', 'partially_validated'], true))
                                    <td>
                                        @if($item['status'] !== 'validated')
                                            <form method="POST"
                                                  action="{{ route('backoffice.inventory.count-sessions.items.count', ['sessionId' => $session['id'], 'itemId' => $item['id']]) }}"
                                                  data-inventory-json-form>
                                                @csrf
                                                <label class="sr-only" for="count-{{ $item['id'] }}">Quantité comptée</label>
                                                <input id="count-{{ $item['id'] }}" name="counted_quantity"
                                                       type="text" inputmode="decimal" pattern="\d+(\.\d{1,3})?"
                                                       value="{{ $item['counted_quantity'] ?? '' }}" required
                                                       placeholder="Quantité comptée">
                                                <label class="sr-only" for="reason-{{ $item['id'] }}">Motif de l’écart</label>
                                                <input id="reason-{{ $item['id'] }}" name="reason" type="text"
                                                       placeholder="Motif si écart">
                                                <button class="button button-small" type="submit">Enregistrer</button>
                                            </form>
                                        @else
                                            <span class="muted">Validé</span>
                                        @endif
                                    </td>
                                @endif
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>

    @if($session['can_manage'] && in_array($session['status'], ['active', 'partially_validated'], true))
        <section class="card">
            <h2>Actions de gestion</h2>
            <div class="actions">
                <form method="POST"
                      action="{{ route('backoffice.inventory.count-sessions.validate', ['sessionId' => $session['id']]) }}"
                      data-inventory-json-form>
                    @csrf
                    <button class="button" type="submit">Valider les éléments comptés</button>
                </form>
                <form method="POST"
                      action="{{ route('backoffice.inventory.count-sessions.cancel', ['sessionId' => $session['id']]) }}"
                      data-inventory-json-form>
                    @csrf
                    <input name="reason" type="text" placeholder="Motif obligatoire de l’annulation" required>
                    <button class="button button-danger" type="submit">Annuler la session</button>
                </form>
            </div>
            <p class="muted">La validation est partielle : les éléments non comptés restent verrouillés dans la session active. L’annulation est logique et libère tous les verrous restants.</p>
        </section>
    @endif

    @if($session['can_manage'] && $session['participants'] !== [])
        <section class="card">
            <h2>Participants</h2>
            <div class="table-wrap">
                <table>
                    <thead><tr><th>Utilisateur</th><th>Rôle</th><th>Participation depuis</th></tr></thead>
                    <tbody>
                        @foreach($session['participants'] as $participant)
                            <tr>
                                <td><code>{{ $participant['user_id'] }}</code></td>
                                <td>{{ str($participant['role'])->replace('_', ' ')->title() }}</td>
                                <td>{{ $participant['joined_at'] ? \Illuminate\Support\Carbon::parse($participant['joined_at'])->format('d/m/Y H:i') : '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>
    @endif
@endsection

@push('styles')
<style>
.sr-only{position:absolute;width:1px;height:1px;padding:0;margin:-1px;overflow:hidden;clip:rect(0,0,0,0);white-space:nowrap;border:0}
td form[data-inventory-json-form]{display:grid;gap:7px;min-width:170px}
td form input{max-width:180px}
</style>
@endpush

@push('scripts')
<script>
document.querySelectorAll('[data-inventory-json-form]').forEach((form) => {
    form.addEventListener('submit', async (event) => {
        event.preventDefault();
        const errorBox = document.querySelector('[data-inventory-error]');
        const button = form.querySelector('button[type="submit"]');
        if (button) button.disabled = true;
        if (errorBox) errorBox.hidden = true;

        try {
            const payload = Object.fromEntries(new FormData(form).entries());
            delete payload._token;
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
                const messages = result.errors
                    ? Object.values(result.errors).flat().join(' ')
                    : (result.message || 'L’opération a échoué.');
                throw new Error(messages);
            }

            window.location.reload();
        } catch (error) {
            if (errorBox) {
                errorBox.textContent = error.message || 'Une erreur est survenue.';
                errorBox.hidden = false;
            }
            if (button) button.disabled = false;
        }
    });
});
</script>
@endpush
