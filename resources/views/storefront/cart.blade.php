@extends('layouts.storefront')

@section('title', 'Mon panier')

@section('content')
<div style="margin-bottom:25px">
    <span class="shop-eyebrow">Votre sélection</span>
    <h1 class="shop-title">Mon panier</h1>
    <p class="shop-muted">Vérifiez vos produits et ajustez les quantités avant de poursuivre.</p>
</div>

<div id="cart-feedback" role="status" aria-live="polite"></div>
<div id="cart-loading" class="shop-card" style="padding:30px;text-align:center">Chargement de votre panier…</div>
<div id="cart-empty" class="shop-card" style="display:none;padding:42px 20px;text-align:center">
    <h2 style="margin-top:0">Votre panier est vide</h2>
    <p class="shop-muted">Parcourez la boutique et ajoutez les produits qui vous intéressent.</p>
    <a class="shop-button" href="{{ route('storefront.products.index') }}">Découvrir la boutique</a>
</div>
<div id="cart-content" style="display:none;grid-template-columns:minmax(0,1fr) 320px;gap:22px;align-items:start">
    <section id="cart-items" style="display:grid;gap:12px"></section>
    <aside class="shop-card" style="padding:20px">
        <h2 style="font-size:18px;margin:0 0 15px">Récapitulatif</h2>
        <div style="display:flex;justify-content:space-between;gap:12px;margin-bottom:10px;font-size:13px">
            <span class="shop-muted">Articles</span><strong id="cart-count">0</strong>
        </div>
        <div style="display:flex;justify-content:space-between;gap:12px;margin-bottom:14px;font-size:13px">
            <span class="shop-muted">Sous-total indicatif</span><strong id="cart-subtotal">0,00 DH</strong>
        </div>
        <p class="shop-muted" style="font-size:11px;border-top:1px solid var(--shop-line);padding-top:13px">Les frais de livraison et le total définitif seront calculés à l’étape de livraison. Aucun frais fictif n’est ajouté ici.</p>
        <a id="cart-checkout" class="shop-button" href="{{ route('customer.login', ['redirect' => route('sales.cart.page')]) }}" style="width:100%;margin-top:9px">Continuer ma commande</a>
        <a class="shop-button shop-button-secondary" href="{{ route('storefront.products.index') }}" style="width:100%;margin-top:9px">Continuer mes achats</a>
        <button id="cart-clear" type="button" class="shop-button shop-button-secondary" style="width:100%;margin-top:9px;border-color:#e5c9c9;color:#9a3535">Vider le panier</button>
    </aside>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    const loading = document.getElementById('cart-loading');
    const empty = document.getElementById('cart-empty');
    const content = document.getElementById('cart-content');
    const itemsElement = document.getElementById('cart-items');
    const feedback = document.getElementById('cart-feedback');
    const csrf = document.querySelector('meta[name="csrf-token"]').content;
    let cart = null;

    const money = value => Number(value || 0).toLocaleString('fr-MA', {minimumFractionDigits:2, maximumFractionDigits:2}) + ' DH';
    const escapeHtml = value => String(value ?? '').replace(/[&<>"']/g, char => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[char]));
    const headers = {'Accept':'application/json','Content-Type':'application/json','X-CSRF-TOKEN':csrf};

    async function request(url, method='GET', body=null) {
        const response = await fetch(url, {method, headers, ...(body ? {body:JSON.stringify(body)} : {})});
        const payload = await response.json();
        if (!response.ok) {
            const message = payload.message || Object.values(payload.errors || {}).flat().join(' ') || 'Une erreur est survenue.';
            if (response.status === 409) await loadCart();
            throw new Error(message);
        }
        return payload.data;
    }

    function render() {
        loading.style.display = 'none';
        const items = cart?.items || [];
        empty.style.display = items.length ? 'none' : 'block';
        content.style.display = items.length ? 'grid' : 'none';
        document.querySelectorAll('[data-cart-count]').forEach(el => el.textContent = items.reduce((sum,item)=>sum+Number(item.quantity),0).toLocaleString('fr-MA'));
        if (!items.length) return;

        itemsElement.innerHTML = items.map(item => {
            const snapshot = item.product || {};
            const unavailable = item.unavailable_reason;
            const displayedUnitPrice = item.requires_acceptance ? (item.accepted_unit_price ?? item.unit_price) : item.unit_price;
            const lineTotal = Number(displayedUnitPrice || 0) * Number(item.quantity || 0);
            const name = snapshot.name || snapshot.product_name || 'Produit';
            const cooperative = item.cooperative_name || 'Coopérative';
            const variant = snapshot.variant_label || snapshot.variant || '';
            const reasonLabels = {
                cooperative_unavailable:'Coopérative indisponible : retirez cette ligne pour commander les autres articles.',
                product_deleted:'Produit supprimé.',
                product_unavailable:'Produit indisponible.',
                variant_deleted:'Variante supprimée.',
                variant_unavailable:'Variante indisponible.',
                variant_required:'Une variante doit être sélectionnée.',
                price_change_pending:'Le prix a augmenté. Votre accord est nécessaire.',
                commercial_change_pending:'Les caractéristiques commerciales ont changé. Votre accord est nécessaire.',
                price_and_commercial_change_pending:'Le prix et les caractéristiques commerciales ont changé. Votre accord est nécessaire.'
            };
            const alert = unavailable ? '<div class="shop-alert shop-error" style="margin:10px 0;font-size:11px">'+escapeHtml(reasonLabels[unavailable] || 'Cette ligne nécessite votre attention.')+'</div>' : '';
            const accept = item.requires_acceptance ? '<button type="button" data-accept="'+escapeHtml(item.id)+'" class="shop-button shop-button-secondary" style="margin-top:8px">Accepter les changements</button>' : '';
            return '<article class="shop-card" style="padding:16px;display:grid;grid-template-columns:minmax(0,1fr) auto;gap:14px">'+
                '<div><span class="shop-pill">'+escapeHtml(cooperative)+'</span><h3 style="margin:8px 0 3px;font-size:15px">'+escapeHtml(name)+'</h3>'+
                (variant ? '<div class="shop-muted" style="font-size:11px">'+escapeHtml(variant)+'</div>' : '')+
                '<div class="shop-muted" style="font-size:11px;margin-top:4px">'+money(displayedUnitPrice)+' / '+escapeHtml(snapshot.unit_label || item.unit_code || 'unité')+'</div>'+
                alert+
                '<div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;margin-top:12px">'+
                '<label style="font-size:11px;font-weight:700" for="qty-'+escapeHtml(item.id)+'">Quantité</label>'+
                '<input id="qty-'+escapeHtml(item.id)+'" data-quantity="'+escapeHtml(item.id)+'" class="shop-input" style="width:112px;min-height:36px" type="number" min="0.001" max="100" step="0.001" value="'+escapeHtml(item.quantity)+'" '+(unavailable && !item.requires_acceptance ? 'disabled' : '')+'>'+
                '<button type="button" data-update="'+escapeHtml(item.id)+'" class="shop-button shop-button-secondary">Mettre à jour</button>'+
                '<button type="button" data-remove="'+escapeHtml(item.id)+'" class="shop-button shop-button-secondary">Retirer</button></div>'+accept+'</div>'+
                '<strong style="white-space:nowrap">'+money(lineTotal)+'</strong></article>';
        }).join('');

        const subtotal = items.reduce((sum,item)=>sum+(Number((item.requires_acceptance ? (item.accepted_unit_price ?? item.unit_price) : item.unit_price) || 0)*Number(item.quantity || 0)),0);
        document.getElementById('cart-count').textContent = items.length;
        document.getElementById('cart-subtotal').textContent = money(subtotal);
        const hasUnavailable = items.some(item => item.unavailable_reason && !item.requires_acceptance);
        const checkout = document.getElementById('cart-checkout');
        checkout.style.opacity = hasUnavailable ? '.55' : '1';
        checkout.setAttribute('aria-disabled', String(hasUnavailable));
        checkout.title = hasUnavailable ? 'Retirez ou corrigez les lignes indisponibles avant de poursuivre.' : '';
    }

    async function loadCart() {
        try {
            cart = await request(@json(route('sales.cart.show')));
            render();
        } catch (error) {
            loading.style.display = 'none';
            feedback.className = 'shop-alert shop-error';
            feedback.textContent = error.message;
        }
    }

    itemsElement.addEventListener('click', async event => {
        const updateId = event.target.dataset.update;
        const removeId = event.target.dataset.remove;
        const acceptId = event.target.dataset.accept;
        const id = updateId || removeId || acceptId;
        if (!id || !cart) return;
        feedback.className = '';
        feedback.textContent = '';
        try {
            if (updateId) {
                const quantity = document.querySelector('[data-quantity="'+CSS.escape(id)+'"]').value;
                if (!/^\d{1,3}(\.\d{1,3})?$/.test(quantity) || Number(quantity)<=0 || Number(quantity)>100) throw new Error('Quantité invalide : maximum 100, trois décimales.');
                cart = await request(@json(route('sales.cart.items.update', ['itemId' => '__ITEM__'])).replace('__ITEM__', id), 'PATCH', {quantity, expected_version:cart.version});
            } else if (removeId) {
                cart = await request(@json(route('sales.cart.items.destroy', ['itemId' => '__ITEM__'])).replace('__ITEM__', id), 'DELETE', {expected_version:cart.version});
            } else {
                cart = await request(@json(route('sales.cart.items.accept-current', ['itemId' => '__ITEM__'])).replace('__ITEM__', id), 'POST', {expected_version:cart.version});
            }
            render();
            feedback.className = 'shop-alert';
            feedback.textContent = 'Panier mis à jour.';
        } catch (error) {
            feedback.className = 'shop-alert shop-error';
            feedback.textContent = error.message;
        }
    });

    document.getElementById('cart-clear').addEventListener('click', async () => {
        if (!cart || !window.confirm('Voulez-vous vraiment vider le panier ?')) return;
        try {
            cart = await request(@json(route('sales.cart.clear')), 'DELETE', {expected_version:cart.version});
            render();
            feedback.className = 'shop-alert';
            feedback.textContent = 'Votre panier est vide.';
        } catch (error) {
            feedback.className = 'shop-alert shop-error';
            feedback.textContent = error.message;
        }
    });

    loadCart();
});
</script>
<style>
@media(max-width:760px){#cart-content{grid-template-columns:1fr!important}#cart-content aside{order:-1}}
</style>
@endpush
