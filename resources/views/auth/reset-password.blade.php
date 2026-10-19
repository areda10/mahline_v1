<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Réinitialiser le mot de passe - MAHLINE</title>
</head>
<body>
    <h1>Réinitialiser le mot de passe</h1>

    @if ($errors->any())
        <ul>
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    @endif

    <form method="POST" action="{{ route('password.update') }}">
        @csrf

        <input
            type="hidden"
            name="token"
            value="{{ old('token', $token) }}"
        >

        <div>
            <label for="email">Adresse e-mail</label>
            <input
                id="email"
                type="email"
                name="email"
                value="{{ old('email', $email) }}"
                autocomplete="email"
                required
            >
        </div>

        <div>
            <label for="password">Nouveau mot de passe</label>
            <input
                id="password"
                type="password"
                name="password"
                autocomplete="new-password"
                minlength="12"
                maxlength="128"
                required
            >
        </div>

        <div>
            <label for="password_confirmation">Confirmer le mot de passe</label>
            <input
                id="password_confirmation"
                type="password"
                name="password_confirmation"
                autocomplete="new-password"
                minlength="12"
                maxlength="128"
                required
            >
        </div>

        <button type="submit">Réinitialiser le mot de passe</button>
    </form>
</body>
</html>