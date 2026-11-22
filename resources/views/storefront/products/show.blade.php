@extends('layouts.storefront')

@section('title', $product->name)
@section('meta_description', \Illuminate\Support\Str::limit(strip_tags((string) $product->description), 155))

@section('content')
<div style="margin-bottom:18px;font-size:12px"><a href="{{ route('storefront.products.index') }}" style="color:#52675c">Boutique</a> <span class="shop-muted">/</span> {{ $product->name }}</div>

<div style="display:grid;grid-template-columns:minmax(0,1.05fr) minmax(0,.95fr);gap:34px;align-items:start">
    <section>
        @if ($product->images->isNotEmpty())
            <div class="shop-card" style="padding:10px">
                <img id="product-main-image" src="{{ route('media.variant', ['media' => $product->primaryImage ?? $product->images->first(), 'variant' => 'large']) }}" alt="{{ $product->name }}" style="width:100%;max-height:560px;object-fit:contain;background:#f1f4f0;border-radius:10px">
            </div>
            @if ($product->images->count() > 1)
                <div style="display:flex;gap:9px;overflow:auto;margin-top:10px">
                    @foreach ($product->images as $image)
                        <button type="button" data-product-image="{{ route('media.variant', ['media' => $image, 'variant' => 'medium']) }}" style="width:76px;height:76px;flex:0 0 76px;padding:3px;border:1px solid #dfe6df;border-radius:8px;background:white;cursor:pointer">
                            <img src="{{ route('media.variant', ['media' => $image, 'variant' => 'thumbnail']) }}" alt="" style="width:100%;height:100%;object-fit:cover;border-radius:5px">
                        </button>
                    @endforeach
                </div>
            @endif
        @else
            <div class="shop-placeholder" style="aspect-ratio:4/3;border-radius:14px">Image à venir</div>
        @endif
    </section>

    <section class="shop-card" style="padding:clamp(20px,4vw,34px)">
        <span class="shop-eyebrow">{{ $product->cooperative->name }}</span>
        <h1 class="shop-title" style="font-size:clamp(26px,3vw,36px)">{{ $product->name }}</h1>
        <div class="shop-price" id="selected-price" style="font-size:26px">
            {{ number_format((float) ($product->price ?? 0), 2, ',', ' ') }} DH
        </div>
        <p class="shop-muted" id="selected-unit" style="font-size:12px;margin:3px 0 20px">
            {{ $product->unit?->label ? 'Par '.$product->unit->label : '' }}
        </p>

        @if ($product->categories->isNotEmpty())
            <div style="display:flex;gap:7px;flex-wrap:wrap;margin-bottom:18px">
                @foreach ($product->categories as $category)
                    <a class="shop-pill" href="{{ route('storefront.products.index', ['category_id' => $category->getKey()]) }}" style="text-decoration:none">{{ $category->name }}</a>
                @endforeach
            </div>
        @endif

        @if ($product->description)
            <div style="padding:17px 0;border-top:1px solid var(--shop-line);border-bottom:1px solid var(--shop-line);white-space:pre-line">{{ $product->description }}</div>
        @endif

        @if ($hasVariants)
            <div style="margin-top:20px">
                <label class="shop-label" for="product-variant">Choisir une variante</label>
                <select class="shop-input" id="product-variant" required>
                    <option value="" data-price="{{ $product->price }}" data-unit="{{ $product->unit?->label ?? '' }}">Sélectionnez une option</option>
                    @foreach ($product->activeVariants as $variant)
                        <option value="{{ $variant->getKey() }}" data-price="{{ $variant->price ?? $product->price }}" data-unit="{{ $variant->unit?->label }}" data-label="{{ $variant->label }}">
                            {{ $variant->label }} — {{ number_format((float) ($variant->price ?? $product->price), 2, ',', ' ') }} DH / {{ $variant->unit?->label }}
                        </option>
                    @endforeach
                </select>
                <p class="shop-muted" style="font-size:11px;margin:6px 0 0">Chaque variante possède son propre prix et son unité de vente.</p>
            </div>
        @else
            <input type="hidden" id="product-variant" value="">
        @endif

        <div style="display:flex;gap:12px;align-items:end;margin-top:18px">
            <div style="width:130px">
                <label class="shop-label" for="product-quantity">Quantité</label>
                <input class="shop-input" id="product-quantity" type="number" min="0.001" max="100" step="0.001" value="1.000" required>
            </div>
            <button class="shop-button" id="add-to-cart" type="button" style="flex:1;min-height:43px">Ajouter au panier</button>
        </div>
        <p id="add-cart-feedback" role="status" class="shop-muted" style="font-size:12px;min-height:20px;margin:10px 0 0"></p>

        <div style="display:flex;gap:10px;align-items:center;padding-top:18px;margin-top:10px;border-top:1px solid var(--shop-line)">
            @if ($product->cooperative->logo)
                <img src="{{ route('media.variant', ['media' => $product->cooperative->logo, 'variant' => 'thumbnail']) }}" alt="" style="width:42px;height:42px;border-radius:50%;object-fit:cover">
            @endif
            <div>
                <strong style="font-size:12px">{{ $product->cooperative->name }}</strong>
                <div class="shop-muted" style="font-size:11px">Produit proposé par une coopérative active</div>
            </div>
        </div>
    </section>
</div>

@if ($relatedProducts->isNotEmpty())
    <section style="margin-top:44px">
        <h2 style="font-size:21px;margin-bottom:16px">À découvrir également</h2>
        <div class="shop-grid">
            @foreach ($relatedProducts as $related)
                <article class="shop-card">
                    <a href="{{ route('storefront.products.show', $related) }}" style="text-decoration:none">
                        @if ($related->primaryImage)
                            <img class="shop-product-image" loading="lazy" src="{{ route('media.variant', ['media' => $related->primaryImage, 'variant' => 'medium']) }}" alt="{{ $related->name }}">
                        @else
                            <div class="shop-placeholder">Image à venir</div>
                        @endif
                        <div class="shop-product-body">
                            <h3 style="font-size:14px;margin:0 0 7px">{{ $related->name }}</h3>
                            <div class="shop-price">{{ number_format((float) $related->price, 2, ',', ' ') }} DH</div>
                        </div>
                    </a>
                </article>
            @endforeach
        </div>
    </section>
@endif
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    const variant = document.getElementById('product-variant');
    const price = document.getElementById('selected-price');
    const unit = document.getElementById('selected-unit');
    const add = document.getElementById('add-to-cart');
    const feedback = document.getElementById('add-cart-feedback');
    const quantity = document.getElementById('product-quantity');

    variant?.addEventListener('change', () => {
        const option = variant.options[variant.selectedIndex];
        if (price && option.dataset.price) {
            price.textContent = Number(option.dataset.price).toLocaleString('fr-MA', {minimumFractionDigits:2, maximumFractionDigits:2}) + ' DH';
        }
        if (unit) unit.textContent = option.dataset.unit ? 'Par ' + option.dataset.unit : '';
    });

    document.querySelectorAll('[data-product-image]').forEach((button) => {
        button.addEventListener('click', () => {
            const main = document.getElementById('product-main-image');
            if (main) main.src = button.dataset.productImage;
        });
    });

    add?.addEventListener('click', async () => {
        feedback.textContent = '';
        const variantId = variant?.value || null;
        if (variant?.tagName === 'SELECT' && !variantId) {
            feedback.textContent = 'Veuillez choisir une variante.';
            return;
        }
        const amount = Number(quantity.value);
        if (!Number.isFinite(amount) || amount <= 0 || amount > 100 || !/^\d{1,3}(\.\d{1,3})?$/.test(quantity.value)) {
            feedback.textContent = 'La quantité doit être comprise entre 0,001 et 100, avec trois décimales maximum.';
            return;
        }

        add.disabled = true;
        add.textContent = 'Ajout…';
        try {
            const headers = {'Accept':'application/json','Content-Type':'application/json','X-CSRF-TOKEN':document.querySelector('meta[name="csrf-token"]').content};
            const cartResponse = await fetch(@json(route('sales.cart.show')), {headers:{'Accept':'application/json'}});
            const cartPayload = await cartResponse.json();
            if (!cartResponse.ok) throw new Error(cartPayload.message || 'Le panier est inaccessible.');
            const response = await fetch(@json(route('sales.cart.items.store')), {
                method:'POST', headers,
                body:JSON.stringify({
                    product_id:@json((string) $product->getKey()),
                    product_variant_id:variantId,
                    quantity:quantity.value,
                    expected_version:cartPayload.data.version
                })
            });
            const payload = await response.json();
            if (!response.ok) throw new Error(payload.message || Object.values(payload.errors || {}).flat().join(' ') || 'Impossible d’ajouter ce produit.');
            window.location.href = @json(route('sales.cart.page'));
        } catch (error) {
            feedback.textContent = error.message;
            add.disabled = false;
            add.textContent = 'Ajouter au panier';
        }
    });
});
</script>
@endpush
