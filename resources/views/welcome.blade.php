@extends('layouts.storefront')

@section('title', 'Accueil')

@section('content')
<section style="display:grid;grid-template-columns:minmax(0,1.1fr) minmax(260px,.9fr);gap:35px;align-items:center;padding:clamp(25px,6vw,72px) 0">
    <div>
        <span class="shop-eyebrow">Le savoir-faire marocain, à portée de clic</span>
        <h1 class="shop-title" style="font-size:clamp(36px,5.8vw,62px);max-width:700px">Des produits authentiques. Des coopératives mises en lumière.</h1>
        <p class="shop-muted" style="font-size:16px;max-width:600px;margin:18px 0 25px">Explorez une sélection de produits issus des coopératives, découvrez leurs savoir-faire et composez votre panier en toute simplicité.</p>
        <div style="display:flex;gap:10px;flex-wrap:wrap">
            <a class="shop-button" href="{{ route('storefront.products.index') }}">Explorer la boutique</a>
            <a class="shop-button shop-button-secondary" href="{{ route('sales.cart.page') }}">Voir mon panier</a>
        </div>
        <p class="shop-muted" style="font-size:11px;margin-top:18px">La découverte et l’ajout au panier sont accessibles sans inscription.</p>
    </div>
    <div class="shop-card" style="padding:30px;background:linear-gradient(145deg,#173f35,#285b4c);color:#fff;min-height:270px;display:flex;flex-direction:column;justify-content:end">
        <div style="font-size:12px;letter-spacing:2px;color:#e4c58f;font-weight:800">MAHLINE MARKETPLACE</div>
        <div style="font-size:31px;line-height:1.15;font-weight:700;margin-top:13px">Un marché collectif, une expérience simple.</div>
        <p style="color:#d5e2db;font-size:13px;margin-bottom:0">Des produits, des catégories et des coopératives réunis au même endroit.</p>
    </div>
</section>
<section class="shop-card" style="padding:25px;margin-bottom:24px">
    <div style="display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:18px">
        <div><span class="shop-eyebrow">01 — Découvrir</span><h2 style="font-size:17px;margin:7px 0">Parcourez la boutique</h2><p class="shop-muted" style="font-size:12px;margin:0">Recherchez par produit, catégorie ou coopérative.</p></div>
        <div><span class="shop-eyebrow">02 — Sélectionner</span><h2 style="font-size:17px;margin:7px 0">Choisissez vos variantes</h2><p class="shop-muted" style="font-size:12px;margin:0">Consultez les prix, unités et options disponibles.</p></div>
        <div><span class="shop-eyebrow">03 — Préparer</span><h2 style="font-size:17px;margin:7px 0">Composez votre panier</h2><p class="shop-muted" style="font-size:12px;margin:0">Ajoutez vos articles avant de vous connecter pour commander.</p></div>
    </div>
</section>
<style>
@media(max-width:720px){.home-steps{grid-template-columns:1fr!important}}
</style>
@endsection
