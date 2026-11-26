<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Mon espace client') — MAHLINE</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        body{margin:0;background:#f4f7f5;color:#20332d;font-family:Arial,sans-serif}
        header{display:flex;justify-content:space-between;align-items:center;gap:16px;flex-wrap:wrap;padding:18px 24px;background:#153d34;color:#fff}
        header a{color:#fff;text-decoration:none}
        .brand{font-size:22px;font-weight:700;letter-spacing:.04em}
        nav{display:flex;align-items:center;gap:16px;flex-wrap:wrap}
        nav form{margin:0}
        .customer-header-actions{display:flex;align-items:center;justify-content:flex-end;gap:18px;flex:1;min-width:0}\n        .customer-profile-link{display:inline-flex;align-items:center;gap:8px}
        .customer-profile-avatar{width:30px;height:30px;border-radius:50%;object-fit:cover;vertical-align:middle}
        nav button{border:1px solid #d9e9e2;border-radius:6px;padding:8px 12px;background:transparent;color:#fff;cursor:pointer}
        main{max-width:1100px;margin:28px auto;padding:0 20px}
        .card{padding:22px;margin-bottom:18px;background:#fff;border:1px solid #e0e8e3;border-radius:10px}
        .muted{color:#65736e}
        .actions{display:flex;align-items:center;flex-wrap:wrap;gap:9px;margin-top:14px}
        .button{display:inline-block;padding:10px 14px;border:1px solid #176b57;border-radius:7px;background:#176b57;color:#fff;font-weight:700;text-decoration:none}
        .button:hover{background:#105342;text-decoration:none}
        .button-secondary{background:#fff;color:#176b57}
        .button-secondary:hover{background:#eef5f0}
        .alert{padding:12px 16px;border-radius:7px;margin-bottom:16px}
        .alert-success{background:#e4f5eb;color:#14532d}
        .alert-error{background:#fff0f0;color:#982d35}
        @media(max-width:650px){header{padding:14px}main{padding:0 12px;margin-top:18px}}
    </style>
    @stack('styles')
</head>
<body>
@php($customerAvatar = auth()->user()?->profileImage)
<header>
    <a class="brand" href="{{ route('customer.dashboard') }}">MAHLINE</a>
    <div class="customer-header-actions">
        @include('notifications._bell')
        <nav aria-label="Espace client">
        <a href="{{ route('storefront.products.index') }}">Boutique</a>
        <a href="{{ route('sales.cart.page') }}">Mon panier</a>
        <a href="{{ route('customer.orders.index') }}">Mes commandes</a>
        <a href="{{ route('customer.dashboard') }}">Mon espace</a>
        <a class="customer-profile-link" href="{{ route('customer.profile.edit') }}">
            @if ($customerAvatar)
                <img class="customer-profile-avatar" src="{{ route('media.private', $customerAvatar) }}?variant=thumbnail" alt="" width="30" height="30">
            @else
                <span class="customer-profile-avatar" aria-hidden="true" style="display:inline-flex;align-items:center;justify-content:center;background:#e4eee8;color:#153d34;font-weight:700">{{ mb_strtoupper(mb_substr(auth()->user()->first_name ?: 'C', 0, 1)) }}</span>
            @endif
            <span>Mon profil</span>
        </a>
        <a href="{{ route('customer.security.password.edit') }}">Sécurité</a>
        <a href="{{ route('customer.security.devices.index') }}">Mes appareils</a>
        <form method="POST" action="{{ route('customer.logout') }}">
            @csrf
            <button type="submit">Déconnexion</button>
        </form>
        </nav>
    </div>
</header>
<main>
    @if (session('success'))
        <div class="alert alert-success" role="status">{{ session('success') }}</div>
    @endif
    @if (session('error'))
        <div class="alert alert-error" role="alert">{{ session('error') }}</div>
    @endif
    @yield('content')
</main>
</body>
</html>
