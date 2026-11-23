@extends('layouts.storefront')

@section('title', 'Finaliser ma commande')

@section('content')
<section style="max-width:900px;margin:0 auto">
    <div style="margin-bottom:24px">
        <span class="shop-eyebrow">Espace client</span>
        <h1 class="shop-title">Finaliser ma commande</h1>
        <p class="shop-muted">{{ $directCartId ? 'Commande directe : seul le produit choisi depuis sa fiche est concerné.' : 'Vérifiez tous les articles de votre panier avant de confirmer.' }}</p>
    </div>
    <div id="checkout-feedback" role="status" aria-live="polite"></div>
    <div id="checkout-loading" class="shop-card" style="padding:24px;text-align:center">Vérification des articles, du stock et des frais de livraison…</div>
    <div id="checkout-content" style="display:none;gap:18px">
        <section class="shop-card" style="padding:22px">
            <h2 style="font-size:19px;margin:0 0 15px">Récapitulatif des produits</h2>
            <div id="checkout-groups" style="display:grid;gap:16px"></div>
        </section>
        <section class="shop-card" style="padding:22px">
            <h2 style="font-size:19px;margin:0 0 15px">Adresse de livraison</h2>
            <div id="checkout-addresses"></div>
            <a href="{{ route('customer.profile.edit') }}" class="shop-button shop-button-secondary" style="margin-top:10px">Gérer mon carnet d’adresses</a>
        </section>
        <aside class="shop-card" style="padding:22px">
            <h2 style="font-size:19px;margin:0 0 15px">Total de la commande</h2>
            <div class="shop-summary-row"><span>Articles</span><strong id="checkout-subtotal">—</strong></div>
            <div class="shop-summary-row"><span>Livraison</span><strong id="checkout-delivery">—</strong></div>
            <div class="shop-summary-row" style="border-top:1px solid var(--shop-line);padding-top:13px;margin-top:10px;font-size:17px"><span>Total à payer</span><strong id="checkout-total">—</strong></div>
            <div class="shop-summary-row" style="font-size:12px;margin-top:14px"><span class="shop-muted">Paiement</span><strong>Paiement à la livraison</strong></div>
            <button id="checkout-confirm" type="button" class="shop-button" style="width:100%;margin-top:18px" disabled>Confirmer la commande</button>
            <p class="shop-muted" style="font-size:11px;margin:10px 0 0">Les montants sont recalculés et revérifiés par le serveur au moment de la confirmation.</p>
        </aside>
    </div>
    <div id="checkout-success" class="shop-card" style="display:none;padding:28px;text-align:center">
        <h2>Votre commande est confirmée</h2>
        <p id="checkout-success-reference" class="shop-muted"></p>
        <a href="{{ route('customer.orders.index') }}" class="shop-button">Consulter mes commandes</a>
    </div>
</section>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    const directCartId = @json($directCartId);
    const idempotencyKey = @json($idempotencyKey);
    const loading = document.getElementById('checkout-loading');
    const content = document.getElementById('checkout-content');
    const feedback = document.getElementById('checkout-feedback');
    const groupsElement = document.getElementById('checkout-groups');
    const addressesElement = document.getElementById('checkout-addresses');
    const confirmButton = document.getElementById('checkout-confirm');
    const csrf = document.querySelector('meta[name="csrf-token"]').content;
    const money = value => Number(value || 0).toLocaleString('fr-MA', {minimumFractionDigits:2, maximumFractionDigits:2}) + ' DH';
    const escapeHtml = value => String(value ?? '').replace(/[&<>"']/g, char => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[char]));
    const previewUrl = @json(route('sales.cart.checkout.preview')) + (directCartId ? '?direct_cart_id=' + encodeURIComponent(directCartId) : '');
    let preview = null;

    async function loadPreview() {
        try {
            const response = await fetch(previewUrl, {headers:{'Accept':'application/json'}});
            const payload = await response.json();
            if (!response.ok) throw new Error(payload.message || Object.values(payload.errors || {}).flat().join(' ') || 'Impossible de préparer la commande.');
            preview = payload.data;
            loading.style.display = 'none';
            content.style.display = 'grid';

            if (preview.checkout_allowed !== true) {
                feedback.className = 'shop-alert shop-error';
                feedback.textContent = 'Certains articles ne sont pas commandables. Consultez votre panier, acceptez les changements nécessaires ou retirez les lignes indisponibles avant de recommencer.';
                confirmButton.disabled = true;
            }

            groupsElement.innerHTML = (preview.groups || []).map(group => {
                const lines = (group.items || []).map(item => {
                    const snapshot = item.product || {};
                    const name = snapshot.name || snapshot.product_name || 'Produit';
                    const variant = snapshot.variant_label || snapshot.variant || '';
                    return '<div style="display:flex;justify-content:space-between;gap:12px;padding:8px 0;border-top:1px solid var(--shop-line)"><div><strong>'+escapeHtml(name)+'</strong>'+(variant ? '<div class="shop-muted" style="font-size:11px">'+escapeHtml(variant)+'</div>' : '')+'<div class="shop-muted" style="font-size:11px">Quantité : '+escapeHtml(item.quantity)+'</div></div><strong>'+money(item.line_total)+'</strong></div>';
                }).join('');
                return '<article><h3 style="font-size:14px;margin:0 0 8px">'+escapeHtml(group.cooperative_name || 'Coopérative')+'</h3>'+lines+'<div style="display:flex;justify-content:space-between;gap:12px;padding-top:8px"><span class="shop-muted">Sous-total</span><strong>'+money(group.subtotal)+'</strong></div><div style="display:flex;justify-content:space-between;gap:12px;padding-top:5px"><span class="shop-muted">Livraison de cette coopérative</span><strong>'+money(group.delivery)+'</strong></div></article>';
            }).join('');

            document.getElementById('checkout-subtotal').textContent = money(preview.subtotal);
            document.getElementById('checkout-delivery').textContent = money(preview.delivery_total);
            document.getElementById('checkout-total').textContent = money(preview.grand_total);

            if (!preview.addresses || preview.addresses.length === 0) {
                addressesElement.innerHTML = '<p class="shop-alert shop-error">Vous devez ajouter une adresse de livraison avant de commander.</p>';
            } else {
                addressesElement.innerHTML = '<label class="shop-label" for="delivery-address">Choisir une adresse</label><select class="shop-input" id="delivery-address" required>'+preview.addresses.map(address => '<option value="'+escapeHtml(address.id)+'" '+(address.is_primary ? 'selected' : '')+'>'+escapeHtml(address.label)+(address.is_primary ? ' — principale' : '')+'</option>').join('')+'</select>';
            }
            confirmButton.disabled = preview.checkout_allowed !== true || !preview.addresses?.length;
        } catch (error) {
            loading.style.display = 'none';
            feedback.className = 'shop-alert shop-error';
            feedback.textContent = error.message;
        }
    }

    confirmButton.addEventListener('click', async () => {
        const address = document.getElementById('delivery-address');
        if (!address?.value) {
            feedback.className = 'shop-alert shop-error';
            feedback.textContent = 'Sélectionnez une adresse de livraison.';
            return;
        }
        confirmButton.disabled = true;
        confirmButton.textContent = 'Confirmation…';
        feedback.className = '';
        feedback.textContent = '';
        try {
            const response = await fetch(@json(route('sales.cart.checkout.confirm')), {
                method:'POST',
                headers:{'Accept':'application/json','Content-Type':'application/json','X-CSRF-TOKEN':csrf},
                body:JSON.stringify({
                    delivery_address_id:address.value,
                    idempotency_key:idempotencyKey,
                    ...(directCartId ? {direct_cart_id:directCartId} : {})
                })
            });
            const payload = await response.json();
            if (!response.ok) throw new Error(payload.message || Object.values(payload.errors || {}).flat().join(' ') || 'La commande n’a pas pu être confirmée.');
            content.style.display = 'none';
            document.getElementById('checkout-success').style.display = 'block';
            document.getElementById('checkout-success-reference').textContent = 'Référence : '+(payload.data.order_reference || payload.data.order_id);
        } catch (error) {
            feedback.className = 'shop-alert shop-error';
            feedback.textContent = error.message;
            confirmButton.disabled = false;
            confirmButton.textContent = 'Confirmer la commande';
        }
    });

    loadPreview();
});
</script>
<style>
.shop-summary-row{display:flex;justify-content:space-between;gap:12px;margin:9px 0;font-size:14px}
#checkout-content{grid-template-columns:minmax(0,1fr) 300px}
#checkout-content aside{grid-column:2;grid-row:1 / span 2}
@media(max-width:760px){#checkout-content{grid-template-columns:1fr}#checkout-content aside{grid-column:auto;grid-row:auto}}
</style>
@endpush
