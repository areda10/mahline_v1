<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>@yield('title', 'MAHLINE') — MAHLINE</title>

    <style>
        :root {
            font-family: Arial, sans-serif;
            color: #202b36;
            background: #f4f6f8;
        }

        * { box-sizing: border-box; }
        body { margin: 0; }
        a { color: #176b57; text-decoration: none; }
        a:hover { text-decoration: underline; }

        .topbar {
            background: #153d34;
            color: #fff;
            padding: 16px 24px;
        }

        .topbar a { color: #fff; }
        .brand { font-size: 22px; font-weight: bold; }
        .container { max-width: 1200px; margin: 28px auto; padding: 0 20px; }
        .nav { display: flex; flex-wrap: wrap; gap: 18px; margin-top: 12px; }
        .card { background: #fff; border: 1px solid #e0e6e9; border-radius: 10px; padding: 22px; margin-bottom: 20px; }
        .page-header { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 16px; margin-bottom: 20px; }
        h1 { margin: 0; font-size: 27px; }
        h2 { font-size: 19px; }
        .muted { color: #65737e; }
        .actions { display: flex; flex-wrap: wrap; gap: 8px; align-items: center; }
        .button { display: inline-block; border: 0; border-radius: 6px; padding: 10px 14px; background: #176b57; color: white; cursor: pointer; font-size: 14px; }
        .button:hover { background: #105342; text-decoration: none; }
        .button-secondary { background: #e8efec; color: #153d34; }
        .button-danger { background: #a52c36; color: #fff; }
        .button-small { padding: 7px 10px; }
        .field { margin-bottom: 17px; }
        label { display: block; font-weight: 600; margin-bottom: 7px; }
        input[type=text], input[type=number], textarea, select {
            display: block; width: 100%; padding: 10px;
            border: 1px solid #bdc8ce; border-radius: 6px;
            background: white; font: inherit;
        }
        textarea { min-height: 100px; resize: vertical; }
        select[multiple] { min-height: 150px; }
        .check { display: flex; gap: 8px; align-items: center; }
        .check label { margin: 0; }
        .error { color: #a52c36; margin-top: 5px; font-size: 14px; }
        .alert { border-radius: 6px; padding: 12px 16px; margin-bottom: 16px; }
        .alert-success { background: #e4f5eb; color: #14532d; }
        .alert-error { background: #fff0f0; color: #9b1c1c; }
        .table-wrap { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; }
        th, td { text-align: left; padding: 12px; border-bottom: 1px solid #e5eaed; vertical-align: top; }
        th { background: #f7f9fa; font-size: 13px; }
        .badge { display: inline-block; border-radius: 20px; padding: 4px 9px; background: #e8efec; font-size: 12px; }
        .badge-inactive { background: #fbe8e8; color: #8b2020; }
        .empty { text-align: center; padding: 28px; color: #65737e; }
        .pagination { margin-top: 18px; }
        .inline-form { display: inline; }
        .footer { padding: 24px; color: #65737e; text-align: center; font-size: 13px; }

        @media (max-width: 650px) {
            .container { padding: 0 12px; margin-top: 18px; }
            .card { padding: 15px; }
            th, td { padding: 9px; }
        }
    </style>
</head>
<body>
<header class="topbar">
    <div class="brand">
        <a href="{{ url('/') }}">MAHLINE</a>
    </div>

    @if(isset($cooperative))
        <div class="muted" style="color:#d9e9e2; margin-top:6px">
            Coopérative : {{ $cooperative->name }}
        </div>

        <nav class="nav" aria-label="Navigation principale">
            @can('viewAny', \App\Domains\Catalog\Models\Catalog::class)
                <a href="{{ route('backoffice.catalogs.index', ['cooperative' => $cooperative->getKey()]) }}">
                    Catalogues
                </a>
            @endcan

            @can('viewAny', \App\Domains\Categories\Models\Category::class)
                <a href="{{ route('backoffice.categories.index', ['cooperative' => $cooperative->getKey()]) }}">
                    Catégories
                </a>
            @endcan

            @can('viewAny', \App\Domains\Products\Models\Product::class)
                <a href="{{ route('backoffice.products.index', ['cooperative' => $cooperative->getKey()]) }}">
                    Produits
                </a>
            @endcan
        </nav>
    @endif
</header>

<main class="container">
    @if(session('success'))
        <div class="alert alert-success" role="status">
            {{ session('success') }}
        </div>
    @endif

    @if(session('error'))
        <div class="alert alert-error" role="alert">
            {{ session('error') }}
        </div>
    @endif

    @yield('content')
</main>

<footer class="footer">
    MAHLINE — Gestion des coopératives
</footer>
</body>
</html>