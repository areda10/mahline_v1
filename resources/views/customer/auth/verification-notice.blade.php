<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Vérification e-mail — MAHLINE</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        body{margin:0;background:#f4f7f5;color:#20332d;font-family:Arial,sans-serif}
        main{max-width:560px;margin:48px auto;padding:32px;background:#fff;border:1px solid #e0e8e3;border-radius:14px}
        h1{color:#153d34}p{line-height:1.5;color:#65736e}
        label{display:block;margin:18px 0 6px;font-weight:600}
        input{width:100%;box-sizing:border-box;padding:12px;border:1px solid #cbd7d0;border-radius:7px;font:inherit}
        button{margin-top:16px;padding:12px 16px;border:0;border-radius:7px;background:#176b57;color:white;font-weight:700;cursor:pointer}
        .notice{padding:12px;background:#e8f4ed;border-radius:7px}
        .error{padding:12px;background:#fff0f0;color:#982d35;border-radius:7px}
        a{color:#176b57}
        @media(max-width:620px){main{margin:16px;padding:22px}}
    </style>
</head>
<body>
<main>
    <a href="{{ url('/') }}">MAHLINE</a>
    <h1>Vérifiez votre adresse e-mail</h1>

    @if (session('status'))
        <p class="notice" role="status">{{ session('status') }}</p>
    @endif
    @if (session('error'))
        <p class="error" role="alert">{{ session('error') }}</p>
    @endif
    @if ($errors->any())
        <p class="error" role="alert">{{ $errors->first() }}</p>
    @endif

    <p>Consultez votre boîte de réception. Le lien de vérification expire après 24 heures et ne peut être utilisé qu'une seule fois.</p>

    <form method="POST" action="{{ route('customer.verification.resend') }}">
        @csrf
        <label for="email">Adresse e-mail utilisée lors de l'inscription</label>
        <input id="email" type="email" name="email" value="{{ old('email') }}" maxlength="254" autocomplete="email" required>
        <button type="submit">Renvoyer le lien</button>
    </form>

    <p><a href="{{ route('authentication.login') }}">Retour à la connexion</a></p>
</main>
</body>
</html>
