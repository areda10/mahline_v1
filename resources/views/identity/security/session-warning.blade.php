@auth
    @php
        $sessionWarningService = app(\App\Domains\Identity\Authentication\Services\SessionService::class);
        $sessionWarningRecord = $sessionWarningService->current(
            auth()->user(),
            request()->session()->getId(),
        );
        $sessionWarningTimeout = $sessionWarningService->inactivityTimeoutMinutes(auth()->user()) * 60;
        $sessionWarningLastActivity = $sessionWarningRecord?->last_activity_at?->timestamp;
        $sessionWarningExtendRoute = auth()->user()->hasRole('super_admin_root')
            ? route('root.backoffice.security.sessions.extend')
            : route('backoffice.security.sessions.extend');
    @endphp

    @if($sessionWarningRecord !== null && $sessionWarningLastActivity !== null)
        <section
            id="mahline-session-warning"
            role="alertdialog"
            aria-modal="true"
            aria-labelledby="mahline-session-warning-title"
            aria-describedby="mahline-session-warning-description"
            data-timeout-seconds="{{ $sessionWarningTimeout }}"
            data-last-activity="{{ $sessionWarningLastActivity }}"
            data-warning-seconds="120"
            data-extend-url="{{ $sessionWarningExtendRoute }}"
            data-login-url="{{ route('authentication.login') }}"
            hidden
            style="position:fixed;inset:0;z-index:9999;background:rgba(10,25,20,.68);display:none;align-items:center;justify-content:center;padding:20px"
        >
            <div style="width:min(100%,480px);background:#fff;border-radius:12px;padding:26px;box-shadow:0 18px 55px rgba(0,0,0,.25)">
                <h2 id="mahline-session-warning-title" style="margin-top:0">Votre session va expirer</h2>
                <p id="mahline-session-warning-description">
                    Pour protéger votre compte, votre session expirera dans
                    <strong id="mahline-session-countdown" aria-live="off">02:00</strong>
                    en raison de l’inactivité.
                </p>
                <p id="mahline-session-warning-error" role="alert" style="color:#9b1c1c" hidden>
                    La prolongation n’a pas abouti. Veuillez réessayer.
                </p>
                <div style="display:flex;flex-wrap:wrap;gap:10px;justify-content:flex-end">
                    <form method="POST" action="{{ route('authentication.logout') }}">
                        @csrf
                        <button type="submit" style="border:1px solid #bdc8ce;border-radius:6px;padding:10px 14px;background:#fff;color:#202b36;cursor:pointer">
                            Me déconnecter
                        </button>
                    </form>
                    <button
                        id="mahline-session-extend"
                        type="button"
                        style="border:0;border-radius:6px;padding:10px 14px;background:#176b57;color:#fff;cursor:pointer"
                    >
                        Continuer ma session
                    </button>
                </div>
            </div>
        </section>

        <script>
            (() => {
                const warning = document.getElementById('mahline-session-warning');

                if (!warning || warning.dataset.initialized === 'true') {
                    return;
                }

                warning.dataset.initialized = 'true';

                const timeoutSeconds = Number(warning.dataset.timeoutSeconds);
                const warningSeconds = Number(warning.dataset.warningSeconds);
                const countdown = document.getElementById('mahline-session-countdown');
                const extendButton = document.getElementById('mahline-session-extend');
                const errorMessage = document.getElementById('mahline-session-warning-error');
                const language = (document.documentElement.lang || 'fr').slice(0, 2).toLowerCase();

                const messages = {
                    fr: {
                        title: 'Votre session va expirer',
                        description: 'Pour protéger votre compte, votre session expirera dans',
                        reason: 'en raison de l’inactivité.',
                        extend: 'Continuer ma session',
                        logout: 'Me déconnecter',
                        error: 'La prolongation n’a pas abouti. Veuillez réessayer.',
                        expired: 'Votre session a expiré. Veuillez vous reconnecter.'
                    },
                    en: {
                        title: 'Your session is about to expire',
                        description: 'To protect your account, your session will expire in',
                        reason: 'because of inactivity.',
                        extend: 'Continue my session',
                        logout: 'Sign out',
                        error: 'The session could not be extended. Please try again.',
                        expired: 'Your session has expired. Please sign in again.'
                    },
                    ar: {
                        title: 'ستنتهي جلستك قريباً',
                        description: 'لحماية حسابك، ستنتهي جلستك خلال',
                        reason: 'بسبب عدم النشاط.',
                        extend: 'متابعة الجلسة',
                        logout: 'تسجيل الخروج',
                        error: 'تعذر تمديد الجلسة. يرجى المحاولة مرة أخرى.',
                        expired: 'انتهت جلستك. يرجى تسجيل الدخول مجدداً.'
                    }
                };

                const dictionary = messages[language] || messages.fr;
                const title = document.getElementById('mahline-session-warning-title');
                const description = document.getElementById('mahline-session-warning-description');
                const buttons = warning.querySelectorAll('button');
                const logoutButton = warning.querySelector('form button');

                title.textContent = dictionary.title;
                description.childNodes[0].textContent = dictionary.description + ' ';
                description.lastChild.textContent = ' ' + dictionary.reason;
                extendButton.textContent = dictionary.extend;
                logoutButton.textContent = dictionary.logout;
                errorMessage.textContent = dictionary.error;

                const loginUrl = warning.dataset.loginUrl;
                let timer = null;
                let focusedBeforeWarning = null;

                const formatTime = (seconds) => {
                    const minutes = Math.floor(seconds / 60);
                    const remainder = seconds % 60;

                    return String(minutes).padStart(2, '0') + ':' +
                        String(remainder).padStart(2, '0');
                };

                const hideWarning = () => {
                    warning.hidden = true;
                    warning.style.display = 'none';

                    if (focusedBeforeWarning && document.contains(focusedBeforeWarning)) {
                        focusedBeforeWarning.focus();
                    }
                };

                const showWarning = () => {
                    if (!warning.hidden) {
                        return;
                    }

                    focusedBeforeWarning = document.activeElement;
                    warning.hidden = false;
                    warning.style.display = 'flex';
                    extendButton.focus();
                };

                const update = () => {
                    const lastActivity = Number(warning.dataset.lastActivity);
                    const elapsed = Math.max(0, Math.floor(Date.now() / 1000 - lastActivity));
                    const remaining = timeoutSeconds - elapsed;

                    if (remaining <= 0) {
                        window.location.assign(loginUrl);
                        return;
                    }

                    countdown.textContent = formatTime(remaining);

                    if (remaining <= warningSeconds) {
                        showWarning();
                    } else if (!warning.hidden) {
                        hideWarning();
                    }
                };

                extendButton.addEventListener('click', async () => {
                    extendButton.disabled = true;
                    errorMessage.hidden = true;

                    try {
                        const response = await fetch(warning.dataset.extendUrl, {
                            method: 'POST',
                            credentials: 'same-origin',
                            headers: {
                                'Accept': 'application/json',
                                'X-Requested-With': 'XMLHttpRequest',
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                            }
                        });

                        if (response.redirected || response.status === 401) {
                            window.location.assign(response.url || loginUrl);
                            return;
                        }

                        if (!response.ok) {
                            throw new Error('Session extension failed.');
                        }

                        const result = await response.json();

                        if (result.status !== 'extended') {
                            throw new Error('Unexpected session extension response.');
                        }

                        warning.dataset.timeoutSeconds = String(result.timeout_seconds);
                        warning.dataset.lastActivity = String(Math.floor(Date.now() / 1000));
                        hideWarning();
                        update();
                    } catch (error) {
                        errorMessage.hidden = false;
                    } finally {
                        extendButton.disabled = false;
                    }
                });

                document.addEventListener('visibilitychange', update);
                window.addEventListener('pageshow', update);

                update();
                timer = window.setInterval(update, 1000);

                window.addEventListener('pagehide', () => {
                    if (timer !== null) {
                        window.clearInterval(timer);
                    }
                }, { once: true });
            })();
        </script>
    @endif
@endauth
