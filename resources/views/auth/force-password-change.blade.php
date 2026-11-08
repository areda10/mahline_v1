<!DOCTYPE html>
<html lang="fr" dir="ltr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Changer le mot de passe — MAHLINE</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="auth-page">
<main class="auth-shell">
    <section class="auth-form-panel">
        <h1>Changer votre mot de passe</h1>
        <p>Votre compte a été activé avec un mot de passe provisoire. Vous devez le remplacer avant d’accéder à MAHLINE.</p>

        @if ($errors->any())
            <div class="alert alert-danger" role="alert">
                <ul>
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form method="POST" action="{{ route('password.force-change.update') }}">
            @csrf
            @method('PUT')
            <div class="field">
                <label for="password">Nouveau mot de passe</label>
                <input id="password" name="password" type="password" required minlength="12" maxlength="128" autocomplete="new-password">
            </div>
            <div class="field">
                <label for="password_confirmation">Confirmer le nouveau mot de passe</label>
                <input id="password_confirmation" name="password_confirmation" type="password" required minlength="12" maxlength="128" autocomplete="new-password">
            </div>
            <button class="button" type="submit">Enregistrer le nouveau mot de passe</button>
        </form>
        <form method="POST" action="{{ route('authentication.logout') }}" style="margin-top: 1rem;">
            @csrf
            <button class="button button-secondary" type="submit">Se déconnecter</button>
        </form>
    </section>
</main>
</body>
</html>
