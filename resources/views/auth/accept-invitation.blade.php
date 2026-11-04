<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Accepter votre invitation - MAHLINE</title>
</head>
<body>
    <main>
        <h1>Bienvenue sur MAHLINE</h1>

        <p>
            Votre compte a été créé. Définissez votre mot de passe
            pour finaliser son activation.
        </p>

        @if ($errors->any())
            <div role="alert">
                @foreach ($errors->all() as $error)
                    <p>{{ $error }}</p>
                @endforeach
            </div>
        @endif

        <form method="POST" action="{{ route('user-invitations.accept') }}">
            @csrf

            <input type="hidden" name="token" value="{{ $token }}">

            <div>
                <label for="password">Nouveau mot de passe</label>
                <input
                    id="password"
                    type="password"
                    name="password"
                    required
                    minlength="12"
                    maxlength="128"
                    autocomplete="new-password"
                >
            </div>

            <div>
                <label for="password_confirmation">
                    Confirmer le mot de passe
                </label>
                <input
                    id="password_confirmation"
                    type="password"
                    name="password_confirmation"
                    required
                    minlength="12"
                    maxlength="128"
                    autocomplete="new-password"
                >
            </div>

            <button type="submit">Activer mon compte</button>
        </form>
    </main>
</body>
</html>