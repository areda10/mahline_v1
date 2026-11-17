<!doctype html>
<html lang="fr">
<head>
<meta charset="utf-8">
<style>
@page { margin: 28px 32px; }
body { font-family: "DejaVu Sans", sans-serif; font-size: 10px; color: #222; }
h1 { font-size: 23px; margin: 0 0 5px; }
h2 { font-size: 14px; margin: 0 0 5px; }
p { line-height: 1.45; }
.header { border-bottom: 2px solid #333; padding-bottom: 12px; margin-bottom: 18px; }
.generated { color: #666; font-size: 8px; }
.product { width: 100%; border-bottom: 1px solid #ccc; padding: 12px 0; page-break-inside: avoid; }
.product-image { width: 112px; height: 90px; object-fit: contain; float: left; margin-right: 14px; }
.product-copy { margin-left: 128px; }
.variants { color: #555; font-size: 9px; }
.footer { position: fixed; bottom: -14px; left: 0; right: 0; text-align: center; font-size: 8px; color: #777; }
</style>
</head>
<body>
<div class="header">
    <h1>{{ $catalog->name }}</h1>
    @if($catalog->description)<p>{{ $catalog->description }}</p>@endif
    <div class="generated">Catalogue généré le {{ $generatedAt->format('d/m/Y H:i') }}</div>
</div>
@forelse($products as $product)
    <div class="product">
        @if($product['image'])
            <img class="product-image" src="{{ $product['image'] }}" alt="">
        @endif
        <div class="product-copy">
            <h2>{{ $product['name'] }}</h2>
            @if($product['description'])<p>{{ $product['description'] }}</p>@endif
            @if($product['variants'])
                <p class="variants"><strong>Variantes :</strong> {{ implode(', ', $product['variants']) }}</p>
            @endif
        </div>
        <div style="clear:both"></div>
    </div>
@empty
    <p>Aucun produit actif dans ce catalogue.</p>
@endforelse
<div class="footer">MAHLINE — Catalogue produits</div>
</body>
</html>
