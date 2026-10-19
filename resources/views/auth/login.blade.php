
<!DOCTYPE html>
<html lang="fr" dir="ltr" data-locale="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#173f35">

    <title>{{ __('Connexion') }} — MAHLINE</title>
    <meta name="description"
          content="Connectez-vous à votre espace professionnel MAHLINE.">

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="auth-page">

    <main class="auth-shell">

        <section class="auth-brand-panel" aria-label="MAHLINE">
            <a class="brand brand-light" href="{{ url('/') }}"
               aria-label="MAHLINE, accueil">
                <span class="brand-symbol" aria-hidden="true">
                    <svg viewBox="0 0 44 44" fill="none">
                        <path d="M7 33V11L22 24L37 11V33"
                              stroke="currentColor"
                              stroke-width="3"
                              stroke-linecap="round"
                              stroke-linejoin="round"/>
                        <path d="M15 33V25M29 33V25"
                              stroke="currentColor"
                              stroke-width="3"
                              stroke-linecap="round"/>
                    </svg>
                </span>
                <span class="brand-name">MAHLINE<span>.</span></span>
            </a>

            <div class="brand-message">
                <span class="eyebrow" data-i18n="brandEyebrow">
                    L'EXCELLENCE AU SERVICE DU COLLECTIF
                </span>

                <h1 data-i18n="brandTitle">
                    Votre savoir-faire mérite un avenir sans frontières.
                </h1>

                <p data-i18n="brandDescription">
                    Gérez vos produits, vos commandes et votre activité
                    dans un espace conçu pour les coopératives ambitieuses.
                </p>

                <div class="brand-divider"></div>

                <div class="brand-note">
                    <span class="brand-note-icon" aria-hidden="true">
                        <svg viewBox="0 0 24 24" fill="none">
                            <path d="M12 3L19 6V11C19 15.5 16 19 12 21
                                     C8 19 5 15.5 5 11V6L12 3Z"
                                  stroke="currentColor" stroke-width="1.6"
                                  stroke-linejoin="round"/>
                            <path d="M9 12L11 14L15 10"
                                  stroke="currentColor" stroke-width="1.6"
                                  stroke-linecap="round"
                                  stroke-linejoin="round"/>
                        </svg>
                    </span>
                    <span data-i18n="brandNote">
                        Un espace professionnel, pensé pour votre croissance.
                    </span>
                </div>
            </div>

            <footer class="brand-footer">
                <span>© {{ date('Y') }} MAHLINE</span>
                <span data-i18n="brandFooter">Bâtir ensemble, aller plus loin.</span>
            </footer>
        </section>

        <section class="auth-form-panel">
            <header class="form-topbar">
                <span class="mobile-brand brand-name">
                    MAHLINE<span>.</span>
                </span>

                <div class="language-switcher"
                     role="group"
                     aria-label="Choisir la langue">
                    <button type="button" data-language="fr"
                            class="language-button is-active"
                            aria-pressed="true">FR</button>
                    <button type="button" data-language="ar"
                            class="language-button"
                            aria-pressed="false">عربي</button>
                    <button type="button" data-language="en"
                            class="language-button"
                            aria-pressed="false">EN</button>
                </div>
            </header>

            <div class="auth-form-container">
                <div class="form-heading">
                    <div class="welcome-mark" aria-hidden="true">
                        <svg viewBox="0 0 24 24" fill="none">
                            <path d="M12 12A4 4 0 1 0 12 4A4 4 0 0 0 12 12Z"
                                  stroke="currentColor" stroke-width="1.6"/>
                            <path d="M4 21C4 16.8 7.2 14 12 14
                                     C16.8 14 20 16.8 20 21"
                                  stroke="currentColor" stroke-width="1.6"
                                  stroke-linecap="round"/>
                        </svg>
                    </div>

                    <span class="eyebrow form-eyebrow"
                          data-i18n="formEyebrow">
                        VOTRE ESPACE PROFESSIONNEL
                    </span>

                    <h2 data-i18n="formTitle">Heureux de vous revoir.</h2>

                    <p data-i18n="formDescription">
                        Connectez-vous pour accéder à votre espace MAHLINE.
                    </p>
                </div>

                @if (session('status'))
                    <div class="notice notice-success" role="status">
                        {{ session('status') }}
                    </div>
                @endif

                @if ($errors->any())
                    <div class="notice notice-error" role="alert">
                        <strong data-i18n="errorTitle">
                            Vérifiez les informations saisies.
                        </strong>
                        <ul>
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <form
                    class="auth-form"
                    method="POST"
                    action="{{ route('authentication.login.store') }}"
                    accept-charset="UTF-8"
                >
                    @csrf

                    <div class="form-field">
                        <label for="email" data-i18n="emailLabel">
                            Adresse e-mail
                        </label>

                        <div class="input-wrapper">
                            <span class="input-icon" aria-hidden="true">
                                <svg viewBox="0 0 24 24" fill="none">
                                    <rect x="3" y="5" width="18" height="14"
                                          rx="3" stroke="currentColor"
                                          stroke-width="1.6"/>
                                    <path d="M4 7L12 13L20 7"
                                          stroke="currentColor"
                                          stroke-width="1.6"
                                          stroke-linecap="round"
                                          stroke-linejoin="round"/>
                                </svg>
                            </span>

                            <input
                                id="email"
                                name="email"
                                type="email"
                                value="{{ old('email') }}"
                                placeholder="nom@exemple.com"
                                autocomplete="username"
                                inputmode="email"
                                maxlength="254"
                                required
                                autofocus
                                dir="ltr"
                                @error('email') aria-invalid="true" @enderror
                                aria-describedby="email-error"
                            >
                        </div>

                        @error('email')
                            <p id="email-error" class="field-error">
                                {{ $message }}
                            </p>
                        @enderror
                    </div>

                    <div class="form-field">
                        <div class="label-row">
                            <label for="password" data-i18n="passwordLabel">
                                Mot de passe
                            </label>

                            <a class="text-link"
                               href="{{ route('password.request') }}"
                               data-i18n="forgotPassword">
                                Mot de passe oublié ?
                            </a>
                        </div>

                        <div class="input-wrapper">
                            <span class="input-icon" aria-hidden="true">
                                <svg viewBox="0 0 24 24" fill="none">
                                    <rect x="4" y="10" width="16" height="11"
                                          rx="2.5" stroke="currentColor"
                                          stroke-width="1.6"/>
                                    <path d="M8 10V7A4 4 0 0 1 16 7V10"
                                          stroke="currentColor"
                                          stroke-width="1.6"
                                          stroke-linecap="round"/>
                                    <path d="M12 14V17"
                                          stroke="currentColor"
                                          stroke-width="1.6"
                                          stroke-linecap="round"/>
                                </svg>
                            </span>

                            <input
                                id="password"
                                name="password"
                                type="password"
                                placeholder="••••••••••••"
                                autocomplete="current-password"
                                required
                                dir="ltr"
                                @error('password') aria-invalid="true" @enderror
                                aria-describedby="password-error"
                            >

                            <button
                                class="password-toggle"
                                type="button"
                                data-password-toggle
                                aria-controls="password"
                                aria-label="Afficher le mot de passe"
                                aria-pressed="false"
                                data-i18n-aria="toggleShow"
                            >
                                <svg viewBox="0 0 24 24" fill="none"
                                     aria-hidden="true">
                                    <path d="M2.5 12S6 5.5 12 5.5
                                             S21.5 12 21.5 12
                                             S18 18.5 12 18.5
                                             S2.5 12 2.5 12Z"
                                          stroke="currentColor"
                                          stroke-width="1.6"/>
                                    <circle cx="12" cy="12" r="3"
                                            stroke="currentColor"
                                            stroke-width="1.6"/>
                                </svg>
                            </button>
                        </div>

                        @error('password')
                            <p id="password-error" class="field-error">
                                {{ $message }}
                            </p>
                        @enderror
                    </div>

                    <button class="submit-button" type="submit">
                        <span data-i18n="submit">Se connecter</span>
                        <svg viewBox="0 0 24 24" fill="none"
                             aria-hidden="true">
                            <path d="M5 12H19M12 5L19 12L12 19"
                                  stroke="currentColor" stroke-width="1.8"
                                  stroke-linecap="round"
                                  stroke-linejoin="round"/>
                        </svg>
                    </button>
                </form>

                <div class="form-security">
                    <svg viewBox="0 0 24 24" fill="none"
                         aria-hidden="true">
                        <path d="M12 3L19 6V11C19 15.5 16 19 12 21
                                 C8 19 5 15.5 5 11V6L12 3Z"
                              stroke="currentColor" stroke-width="1.5"
                              stroke-linejoin="round"/>
                        <path d="M9 12L11 14L15 10"
                              stroke="currentColor" stroke-width="1.5"
                              stroke-linecap="round"
                              stroke-linejoin="round"/>
                    </svg>
                    <span data-i18n="securityNote">
                        Votre connexion est protégée.
                    </span>
                </div>
            </div>

            <footer class="form-footer">
                <span data-i18n="footerText">
                    Une plateforme dédiée aux coopératives.
                </span>
            </footer>
        </section>
    </main>
</body>
</html>
