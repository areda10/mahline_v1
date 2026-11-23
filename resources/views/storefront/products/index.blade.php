@extends('layouts.storefront')

@section('title', 'Boutique')
@section('meta_description', 'Parcourez les produits proposés par les coopératives marocaines.')

@section('content')
<section style="padding:30px 0 24px">
    <span class="shop-eyebrow">Le meilleur du savoir-faire coopératif</span>
    <h1 class="shop-title">La boutique MAHLINE</h1>
    <p class="shop-muted" style="max-width:650px;margin:0">Découvrez des produits proposés par des coopératives marocaines. Parcourez librement la boutique, comparez les offres et ajoutez vos produits au panier sans créer de compte.</p>
</section>

<form method="GET" action="{{ route('storefront.products.index') }}" class="shop-card" style="padding:18px;margin-bottom:24px">
    <div style="display:grid;grid-template-columns:2fr repeat(4,minmax(120px,1fr));gap:12px;align-items:end">
        <div>
            <label class="shop-label" for="q">Recherche</label>
            <input class="shop-input" id="q" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Produit, description, coopérative…">
        </div>
        <div id="categories">
            <label class="shop-label" for="category_id">Catégorie</label>
            <select class="shop-input" id="category_id" name="category_id">
                <option value="">Toutes</option>
                @foreach ($categories as $category)
                    <option value="{{ $category->getKey() }}" @selected(($filters['category_id'] ?? '') === (string) $category->getKey())>{{ $category->name }}</option>
                @endforeach
            </select>
        </div>
        <div id="cooperatives">
            <label class="shop-label" for="cooperative_id">Coopérative</label>
            <select class="shop-input" id="cooperative_id" name="cooperative_id">
                <option value="">Toutes</option>
                @foreach ($cooperatives as $cooperative)
                    <option value="{{ $cooperative->getKey() }}" @selected(($filters['cooperative_id'] ?? '') === (string) $cooperative->getKey())>{{ $cooperative->name }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="shop-label" for="min_price">Prix min. (DH)</label>
            <input class="shop-input" id="min_price" name="min_price" type="number" min="0" step="0.01" value="{{ $filters['min_price'] ?? '' }}">
        </div>
        <div>
            <label class="shop-label" for="max_price">Prix max. (DH)</label>
            <input class="shop-input" id="max_price" name="max_price" type="number" min="0" step="0.01" value="{{ $filters['max_price'] ?? '' }}">
        </div>
    </div>
    <div style="display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;margin-top:13px">
        <div style="display:flex;align-items:center;gap:9px">
            <label class="shop-label" for="sort" style="margin:0">Trier par</label>
            <select class="shop-input" id="sort" name="sort" style="width:auto;min-width:170px">
                <option value="newest" @selected(($filters['sort'] ?? 'newest') === 'newest')>Plus récents</option>
                <option value="price_asc" @selected(($filters['sort'] ?? '') === 'price_asc')>Prix croissant</option>
                <option value="price_desc" @selected(($filters['sort'] ?? '') === 'price_desc')>Prix décroissant</option>
                <option value="name" @selected(($filters['sort'] ?? '') === 'name')>Nom A à Z</option>
            </select>
        </div>
        <div style="display:flex;gap:8px">
            <a class="shop-button shop-button-secondary" href="{{ route('storefront.products.index') }}">Réinitialiser</a>
            <button class="shop-button" type="submit">Appliquer les filtres</button>
        </div>
    </div>
</form>

<div style="display:flex;justify-content:space-between;align-items:end;gap:12px;margin-bottom:14px">
    <div>
        <h2 style="font-size:20px;margin:0">Nos produits</h2>
        <p class="shop-muted" style="margin:3px 0 0;font-size:12px">{{ $products->total() }} résultat(s)</p>
    </div>
</div>

@if ($products->isEmpty())
    <div class="shop-card" style="padding:42px 20px;text-align:center">
        <h2 style="margin:0 0 8px">Aucun produit trouvé</h2>
        <p class="shop-muted">Modifiez votre recherche ou retirez certains filtres.</p>
        <a class="shop-button shop-button-secondary" href="{{ route('storefront.products.index') }}">Voir tous les produits</a>
    </div>
@else
    <div class="shop-grid">
        @foreach ($products as $product)
            @php
                $variantPrices = $product->activeVariants
                    ->map(static fn ($variant) => $variant->price ?? $product->price)
                    ->filter(static fn ($price) => $price !== null);
                $displayPrice = $variantPrices->isNotEmpty()
                    ? $variantPrices->min()
                    : $product->price;
            @endphp
            <article class="shop-card">
                <a href="{{ route('storefront.products.show', $product) }}" style="display:block;text-decoration:none">
                    @if ($product->primaryImage)
                        <img class="shop-product-image" loading="lazy" src="{{ route('media.variant', ['media' => $product->primaryImage, 'variant' => 'medium']) }}" alt="{{ $product->name }}">
                    @else
                        <div class="shop-placeholder">Image à venir</div>
                    @endif
                </a>
                <div class="shop-product-body">
                    <span class="shop-pill">{{ $product->cooperative->name }}</span>
                    <h3 style="font-size:15px;line-height:1.35;margin:10px 0 7px">
                        <a href="{{ route('storefront.products.show', $product) }}" style="text-decoration:none">{{ $product->name }}</a>
                    </h3>
                    <div class="shop-price">@if ($product->activeVariants->isNotEmpty()) À partir de @endif {{ number_format((float) $displayPrice, 2, ',', ' ') }} DH</div>
                    @if ($product->unit)
                        <div class="shop-muted" style="font-size:11px">Par {{ $product->unit->label }}</div>
                    @elseif ($product->activeVariants->isNotEmpty())
                        <div class="shop-muted" style="font-size:11px">Plusieurs variantes disponibles</div>
                    @endif
                    <a href="{{ route('storefront.products.show', $product) }}" class="shop-button shop-button-secondary" style="width:100%;margin-top:13px">Voir le produit</a>
                    @if ($product->activeVariants->isEmpty())
                        <form method="POST" action="{{ route('sales.cart.checkout.direct.start') }}" style="margin-top:8px">
                            @csrf
                            <input type="hidden" name="product_id" value="{{ $product->getKey() }}">
                            <input type="hidden" name="quantity" value="1.000">
                            <button class="shop-button" type="submit" style="width:100%">Commander</button>
                        </form>
                    @else
                        <a href="{{ route('storefront.products.show', $product) }}" class="shop-button" style="width:100%;margin-top:8px">Commander — choisir une variante</a>
                    @endif
                </div>
            </article>
        @endforeach
    </div>
    <div style="margin-top:24px">{{ $products->links() }}</div>
@endif
@endsection
