<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mot de passe oublié - MAHLINE</title>
</head>
<body>
    <h1>Mot de passe oublié</h1>

    @if (session('status'))
        <p role="status">{{ session('status') }}</p>
    @endif

    @if ($errors->any())
        <ul>
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    @endif

    <p>Indiquez votre adresse e-mail pour recevoir un lien de réinitialisation.</p>

    <form method="POST" action="{{ route('password.email') }}">
        @csrf

        <div>
            <label for="email">Adresse e-mail</label>
            <input
                id="email"
                type="email"
                name="email"
                value="{{ old('email') }}"
                autocomplete="email"
                required
                autofocus
            >
        </div>

        <button type="submit">Envoyer le lien</button>
    </form>

    <p>
        <a href="{{ route('authentication.login') }}">Retour à la connexion</a>
    </p>
</body>
</html>