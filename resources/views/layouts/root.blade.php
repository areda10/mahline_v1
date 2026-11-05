<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>@yield('title', 'MAHLINE Root') — MAHLINE</title>

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
        .nav { display: flex; flex-wrap: wrap; gap: 18px; margin-top: 12px; }
        .container { max-width: 1200px; margin: 28px auto; padding: 0 20px; }
        .card { background: #fff; border: 1px solid #e0e6e9; border-radius: 10px; padding: 22px; margin-bottom: 20px; }
        .page-header { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 16px; margin-bottom: 20px; }
        h1 { margin: 0; font-size: 27px; }
        h2 { font-size: 19px; }
        .muted { color: #65737e; }
        .actions { display: flex; flex-wrap: wrap; gap: 8px; align-items: center; }
        .button { display: inline-block; border: 0; border-radius: 6px; padding: 10px 14px; background: #176b57; color: white; cursor: pointer; font-size: 14px; }
        .button:hover { background: #105342; text-decoration: none; }
        .button-secondary { background: #e8efec; color: #153d34; }
        .button-secondary:hover { background: #dbe7e2; }
        .button-danger { background: #a83232; color: #fff; }
        .button-danger:hover { background: #872626; }
        .button-small { padding: 7px 10px; font-size: 13px; }
        .form-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 18px; }
        .field, .form-grid label { display: flex; flex-direction: column; gap: 7px; font-weight: 600; }
        input, select, textarea { width: 100%; min-width: 0; border: 1px solid #cbd5da; border-radius: 6px; padding: 10px 11px; background: #fff; color: #202b36; font: inherit; font-weight: 400; }
        input:focus, select:focus, textarea:focus { outline: 2px solid #176b57; outline-offset: 1px; border-color: #176b57; }
        .error { color: #9b1c1c; margin: 8px 0; }
        .pagination { margin-top: 18px; }
        .pagination nav { display: flex; justify-content: space-between; gap: 12px; }
        .inline-form { display: inline-flex; }
        dl { display: grid; grid-template-columns: minmax(120px, 180px) 1fr; gap: 10px 18px; margin: 0 0 20px; }
        dt { font-weight: 700; }
        dd { margin: 0; }
        .table-wrap { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; }
        th, td { text-align: left; padding: 12px; border-bottom: 1px solid #e5eaed; vertical-align: top; }
        th { background: #f7f9fa; font-size: 13px; }
        .empty { text-align: center; padding: 28px; color: #65737e; }
        .alert { border-radius: 6px; padding: 12px 16px; margin-bottom: 16px; }
        .alert-success { background: #e4f5eb; color: #14532d; }
        .alert-error { background: #fff0f0; color: #9b1c1c; }
        .footer { padding: 24px; color: #65737e; text-align: center; font-size: 13px; }

        @media (max-width: 650px) {
            .container { padding: 0 12px; margin-top: 18px; }
            .card { padding: 15px; }
            th, td { padding: 9px; }
        }
    </style>

    @stack('styles')
</head>
<body>
<header class="topbar">
    <div class="brand">
        <a href="{{ route('root.backoffice.dashboard') }}">MAHLINE ROOT</a>
    </div>

    @auth
        @if(auth()->user()->hasRole('super_admin_root'))
            <nav class="nav" aria-label="Navigation Root">
                <a href="{{ route('root.backoffice.dashboard') }}">Tableau de bord</a>
                <a href="{{ route('root.backoffice.categories.index') }}">Catégories globales</a>
                <a href="{{ route('root.backoffice.cooperatives.index') }}">Coopératives</a>
                <a href="{{ route('root.backoffice.users.index') }}">Utilisateurs</a>
            </nav>
        @endif
    @endauth
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
    MAHLINE — Administration centrale
</footer>
</body>
</html>