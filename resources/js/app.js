
/**
 * MAHLINE — Authentication interface
 *
 * Responsibilities:
 * - FR / AR / EN interface translations
 * - RTL support for Arabic
 * - Password visibility toggle
 *
 * Authentication remains in Laravel's Identity domain.
 */

document.addEventListener("DOMContentLoaded", () => {
    const root = document.documentElement;

    const translations = {
        fr: {
            brandEyebrow: "L'EXCELLENCE AU SERVICE DU COLLECTIF",
            brandTitle: "Votre savoir-faire mérite un avenir sans frontières.",
            brandDescription:
                "Gérez vos produits, vos commandes et votre activité dans un espace conçu pour les coopératives ambitieuses.",
            brandNote:
                "Un espace professionnel, pensé pour votre croissance.",
            brandFooter: "Bâtir ensemble, aller plus loin.",
            formEyebrow: "VOTRE ESPACE PROFESSIONNEL",
            formTitle: "Heureux de vous revoir.",
            formDescription:
                "Connectez-vous pour accéder à votre espace MAHLINE.",
            errorTitle: "Vérifiez les informations saisies.",
            emailLabel: "Adresse e-mail",
            passwordLabel: "Mot de passe",
            forgotPassword: "Mot de passe oublié ?",
            submit: "Se connecter",
            securityNote: "Votre connexion est protégée.",
            footerText: "Une plateforme dédiée aux coopératives.",
            toggleShow: "Afficher le mot de passe",
            toggleHide: "Masquer le mot de passe",
            documentTitle: "Connexion — MAHLINE"
        },

        en: {
            brandEyebrow: "EXCELLENCE THROUGH COLLABORATION",
            brandTitle: "Your craftsmanship deserves a future without borders.",
            brandDescription:
                "Manage your products, orders and business in a workspace designed for ambitious cooperatives.",
            brandNote:
                "A professional workspace designed for your growth.",
            brandFooter: "Building together, going further.",
            formEyebrow: "YOUR PROFESSIONAL WORKSPACE",
            formTitle: "Welcome back.",
            formDescription:
                "Sign in to access your MAHLINE workspace.",
            errorTitle: "Please check the information you entered.",
            emailLabel: "Email address",
            passwordLabel: "Password",
            forgotPassword: "Forgot your password?",
            submit: "Sign in",
            securityNote: "Your sign-in is protected.",
            footerText: "A platform dedicated to cooperatives.",
            toggleShow: "Show password",
            toggleHide: "Hide password",
            documentTitle: "Sign in — MAHLINE"
        },

        ar: {
            brandEyebrow: "التميز من خلال التعاون",
            brandTitle: "حرفيتكم تستحق مستقبلاً بلا حدود.",
            brandDescription:
                "أديروا منتجاتكم وطلباتكم وأنشطتكم عبر مساحة عمل مصممة للتعاونيات الطموحة.",
            brandNote: "مساحة عمل احترافية تدعم نموكم.",
            brandFooter: "نبني معاً ونحقق المزيد.",
            formEyebrow: "مساحتكم المهنية",
            formTitle: "يسعدنا عودتكم.",
            formDescription:
                "سجلوا الدخول للوصول إلى مساحة عمل MAHLINE.",
            errorTitle: "يرجى التحقق من المعلومات المدخلة.",
            emailLabel: "البريد الإلكتروني",
            passwordLabel: "كلمة المرور",
            forgotPassword: "هل نسيتم كلمة المرور؟",
            submit: "تسجيل الدخول",
            securityNote: "تسجيل الدخول الخاص بكم محمي.",
            footerText: "منصة مخصصة للتعاونيات.",
            toggleShow: "إظهار كلمة المرور",
            toggleHide: "إخفاء كلمة المرور",
            documentTitle: "تسجيل الدخول — MAHLINE"
        }
    };

    function setLanguage(language) {
        const selected = translations[language]
            ? language
            : "fr";

        const dictionary = translations[selected];

        root.lang = selected;
        root.dir = selected === "ar" ? "rtl" : "ltr";
        root.dataset.locale = selected;

        document.title = dictionary.documentTitle;

        document.querySelectorAll("[data-i18n]").forEach((element) => {
            const key = element.dataset.i18n;

            if (Object.prototype.hasOwnProperty.call(dictionary, key)) {
                element.textContent = dictionary[key];
            }
        });

        document.querySelectorAll("[data-i18n-aria]").forEach((element) => {
            const key = element.dataset.i18nAria;

            if (Object.prototype.hasOwnProperty.call(dictionary, key)) {
                element.setAttribute("aria-label", dictionary[key]);
            }
        });

        document.querySelectorAll("[data-language]").forEach((button) => {
            const active = button.dataset.language === selected;

            button.classList.toggle("is-active", active);
            button.setAttribute("aria-pressed", String(active));
        });

        document.querySelectorAll("[data-password-toggle]").forEach((button) => {
            const input = document.getElementById(
                button.getAttribute("aria-controls")
            );

            if (!input) {
                return;
            }

            const key = input.type === "password"
                ? "toggleShow"
                : "toggleHide";

            button.setAttribute("aria-label", dictionary[key]);
        });

        try {
            localStorage.setItem("mahline-locale", selected);
        } catch {
            // Language switching still works if storage is unavailable.
        }
    }

    document.querySelectorAll("[data-language]").forEach((button) => {
        button.addEventListener("click", () => {
            setLanguage(button.dataset.language);
        });
    });

    document.querySelectorAll("[data-password-toggle]").forEach((button) => {
        button.addEventListener("click", () => {
            const input = document.getElementById(
                button.getAttribute("aria-controls")
            );

            if (!input) {
                return;
            }

            const showPassword = input.type === "password";

            input.type = showPassword ? "text" : "password";
            button.setAttribute("aria-pressed", String(showPassword));

            const language = root.dataset.locale || "fr";
            const dictionary = translations[language];

            const label = showPassword
                ? dictionary.toggleHide
                : dictionary.toggleShow;

            button.setAttribute("aria-label", label);
        });
    });

    let initialLanguage = "fr";

    try {
        const savedLanguage = localStorage.getItem("mahline-locale");

        if (savedLanguage && translations[savedLanguage]) {
            initialLanguage = savedLanguage;
        }
    } catch {
        // Default to French.
    }

    setLanguage(initialLanguage);
});
