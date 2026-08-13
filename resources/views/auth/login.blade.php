<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Connexion - MAHLINE</title>
</head>

<body>

    <h1>Connexion</h1>

    @if ($errors->any())
        <div>
            <ul>
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form
        method="POST"
        action="{{ route('login.authenticate') }}"
    >
        @csrf

        <div>
            <label for="email">
                Email
            </label>

            <input
                id="email"
                type="email"
                name="email"
                value="{{ old('email') }}"
                required
                autofocus
            >
        </div>

        <div>
            <label for="password">
                Password
            </label>

            <input
                id="password"
                type="password"
                name="password"
                required
            >
        </div>

        <button type="submit">
            Se connecter
        </button>
    </form>

</body>
</html>