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
        nav button{border:1px solid #d9e9e2;border-radius:6px;padding:8px 12px;background:transparent;color:#fff;cursor:pointer}
        main{max-width:1100px;margin:28px auto;padding:0 20px}
        .card{padding:22px;margin-bottom:18px;background:#fff;border:1px solid #e0e8e3;border-radius:10px}
        .muted{color:#65736e}
        .alert{padding:12px 16px;border-radius:7px;margin-bottom:16px}
        .alert-success{background:#e4f5eb;color:#14532d}
        .alert-error{background:#fff0f0;color:#982d35}
        @media(max-width:650px){header{padding:14px}main{padding:0 12px;margin-top:18px}}
    </style>
    @stack('styles')
</head>
<body>
<header>
    <a class="brand" href="{{ route('customer.dashboard') }}">MAHLINE</a>
    <nav aria-label="Espace client">
        <a href="{{ route('customer.dashboard') }}">Mon espace</a>
        <a href="{{ route('customer.profile.edit') }}">Mon profil</a>
        <a href="{{ route('customer.security.password.edit') }}">Sécurité</a>
        <form method="POST" action="{{ route('customer.logout') }}">
            @csrf
            <button type="submit">Déconnexion</button>
        </form>
    </nav>
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
