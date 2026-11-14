<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Créer mon compte — MAHLINE</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        body{margin:0;background:#f4f7f5;color:#20332d;font-family:Arial,sans-serif}
        main{max-width:560px;margin:48px auto;padding:32px;background:#fff;border:1px solid #e0e8e3;border-radius:14px}
        h1{margin:0 0 8px;color:#153d34}p{color:#65736e;line-height:1.5}
        label{display:block;margin:16px 0 6px;font-weight:600}
        input{width:100%;box-sizing:border-box;padding:12px;border:1px solid #cbd7d0;border-radius:7px;font:inherit}
        button{width:100%;margin-top:22px;padding:13px;border:0;border-radius:7px;background:#176b57;color:white;font-weight:700;cursor:pointer}
        .errors{padding:12px;background:#fff0f0;color:#982d35;border-radius:7px}
        .field-error{color:#982d35;font-size:13px}
        a{color:#176b57}
        @media(max-width:620px){main{margin:16px;padding:22px}}
    </style>
</head>
<body>
<main>
    <a href="{{ url('/') }}">MAHLINE</a>
    <h1>Créer mon espace client</h1>
    <p>Inscrivez-vous pour découvrir les produits des coopératives et passer vos commandes.</p>

    @if ($errors->any())
        <div class="errors" role="alert">
            <strong>Veuillez vérifier les informations saisies.</strong>
            <ul>
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ route('customer.register.store') }}">
        @csrf
        <label for="first_name">Prénom</label>
        <input id="first_name" name="first_name" value="{{ old('first_name') }}" maxlength="100" autocomplete="given-name" required>

        <label for="last_name">Nom</label>
        <input id="last_name" name="last_name" value="{{ old('last_name') }}" maxlength="100" autocomplete="family-name" required>

        <label for="email">Adresse e-mail</label>
        <input id="email" type="email" name="email" value="{{ old('email') }}" maxlength="254" autocomplete="email" required>

        <label for="telephone">Téléphone</label>
        <input id="telephone" type="tel" name="telephone" value="{{ old('telephone') }}" maxlength="30" autocomplete="tel" placeholder="+212 6..." required>

        <label for="password">Mot de passe</label>
        <input id="password" type="password" name="password" minlength="12" maxlength="128" autocomplete="new-password" required>
        <p>12 caractères minimum, avec majuscule, minuscule, chiffre et caractère spécial.</p>

        <label for="password_confirmation">Confirmer le mot de passe</label>
        <input id="password_confirmation" type="password" name="password_confirmation" minlength="12" maxlength="128" autocomplete="new-password" required>

        <button type="submit">Créer mon compte</button>
    </form>

    <p>Vous avez déjà un compte ? <a href="{{ route('customer.login') }}">Se connecter</a></p>
</main>
</body>
</html>
