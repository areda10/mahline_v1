<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Boutique') — MAHLINE</title>
    <meta name="description" content="@yield('meta_description', 'Découvrez les produits des coopératives marocaines sur MAHLINE.')">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        :root{--shop-green:#173f35;--shop-green-dark:#102f28;--shop-gold:#c9a66b;--shop-bg:#f7f8f5;--shop-ink:#202923;--shop-muted:#718078;--shop-line:#e4e9e4}
        body.shop-page{margin:0;background:var(--shop-bg);color:var(--shop-ink);font-family:Inter,"Segoe UI",Arial,sans-serif;line-height:1.55}
        .shop-topbar{background:var(--shop-green);color:#fff}
        .shop-nav{max-width:1240px;margin:auto;padding:15px 24px;display:flex;align-items:center;justify-content:space-between;gap:22px}
        .shop-brand{font-size:23px;font-weight:800;letter-spacing:2px;color:#fff;text-decoration:none}
        .shop-brand span{color:var(--shop-gold)}
        .shop-links{display:flex;align-items:center;gap:22px;flex-wrap:wrap}
        .shop-links a{color:#fff;text-decoration:none;font-size:13px}
        .shop-links a:hover{text-decoration:underline;text-underline-offset:5px}
        .shop-cart-link{padding:8px 13px;border:1px solid #ffffff55;border-radius:999px}
        .shop-main{max-width:1240px;margin:0 auto;padding:32px 24px 64px}
        .shop-footer{padding:28px 24px;background:#102f28;color:#dbe5df;text-align:center;font-size:12px}
        .shop-eyebrow{color:#9a7a45;font-size:10px;font-weight:800;letter-spacing:2px;text-transform:uppercase}
        .shop-title{margin:7px 0 12px;font-size:clamp(27px,4vw,42px);line-height:1.15;letter-spacing:-1px}
        .shop-muted{color:var(--shop-muted)}
        .shop-card{background:#fff;border:1px solid var(--shop-line);border-radius:14px;overflow:hidden}
        .shop-button{display:inline-flex;align-items:center;justify-content:center;gap:8px;padding:11px 17px;border:1px solid var(--shop-green);border-radius:8px;background:var(--shop-green);color:#fff;text-decoration:none;font-weight:700;font-size:13px;cursor:pointer}
        .shop-button:hover{background:var(--shop-green-dark)}
        .shop-button-secondary{background:#fff;color:var(--shop-green)}
        .shop-button-secondary:hover{background:#f1f5f1}
        .shop-input{width:100%;min-height:43px;padding:9px 12px;border:1px solid #dce4de;border-radius:8px;background:#fff;color:var(--shop-ink)}
        .shop-label{display:block;margin-bottom:6px;font-size:12px;font-weight:700;color:#405047}
        .shop-alert{padding:12px 15px;margin-bottom:18px;border-radius:9px;background:#edf8f1;color:#216447}
        .shop-error{background:#fff0f0;color:#982d35}
        .shop-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:18px}
        .shop-product-image{width:100%;aspect-ratio:4/3;object-fit:cover;background:#eef2ed;display:block}
        .shop-placeholder{display:grid;place-items:center;aspect-ratio:4/3;background:#edf2ed;color:#819087;font-size:12px}
        .shop-product-body{padding:15px}
        .shop-price{font-size:18px;font-weight:800;color:var(--shop-green)}
        .shop-pill{display:inline-flex;padding:4px 9px;border-radius:999px;background:#edf3ee;color:#426052;font-size:10px;font-weight:700}
        @media(max-width:900px){.shop-grid{grid-template-columns:repeat(2,minmax(0,1fr))}}
        @media(max-width:760px){.shop-product-detail{grid-template-columns:1fr!important}}
        @media(max-width:600px){.shop-nav{padding:13px 16px;align-items:flex-start;flex-direction:column;gap:12px}.shop-links{gap:13px}.shop-main{padding:24px 15px 45px}.shop-grid{grid-template-columns:1fr 1fr;gap:10px}.shop-product-body{padding:11px}.shop-product-image,.shop-placeholder{aspect-ratio:1/1}}
        @media(max-width:380px){.shop-grid{grid-template-columns:1fr}}
    </style>
    @stack('styles')
</head>
<body class="shop-page">
<header class="shop-topbar">
    <nav class="shop-nav" aria-label="Navigation principale">
        <a class="shop-brand" href="{{ route('storefront.products.index') }}">MAHLINE<span>.</span></a>
        <div class="shop-links">
            <a href="{{ route('storefront.products.index') }}">Boutique</a>
            <a href="{{ route('storefront.products.index') }}#categories">Catégories</a>
            <a href="{{ route('storefront.products.index') }}#cooperatives">Coopératives</a>
            <a class="shop-cart-link" href="{{ route('sales.cart.page') }}">Panier <span data-cart-count>0</span></a>
            @auth
                @if (auth()->user()->hasRole('customer'))
                    <a href="{{ route('customer.dashboard') }}">Mon compte</a>
                @else
                    <a href="{{ url('/') }}">Mon espace</a>
                @endif
            @else
                <a href="{{ route('customer.login') }}">Connexion</a>
            @endauth
        </div>
    </nav>
</header>
<main class="shop-main">
    @if (session('success'))
        <div class="shop-alert" role="status">{{ session('success') }}</div>
    @endif
    @if (session('error'))
        <div class="shop-alert shop-error" role="alert">{{ session('error') }}</div>
    @endif
    @yield('content')
</main>
<footer class="shop-footer">MAHLINE — Des produits de coopératives marocaines, réunis dans une même boutique.</footer>
@stack('scripts')
<script>
document.addEventListener('DOMContentLoaded', async () => {
    const count = document.querySelector('[data-cart-count]');
    if (!count) return;
    try {
        const response = await fetch(@json(route('sales.cart.show')), {headers:{'Accept':'application/json'}});
        if (!response.ok) return;
        const payload = await response.json();
        count.textContent = (payload.data?.items || []).reduce((sum, item) => sum + Number(item.quantity || 0), 0).toLocaleString('fr-MA');
    } catch (_) {}
});
</script>
</body>
</html>
