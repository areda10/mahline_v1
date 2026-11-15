@extends('layouts.root')

@section('title', 'Sécurité — déverrouillage des accès')

@section('content')
    <div class="page-header">
        <div>
            <h1>Déverrouillage des accès</h1>
            <p class="muted">Réinitialiser les compteurs de protection après vérification administrative.</p>
        </div>
    </div>

    <section class="card" aria-labelledby="lockout-warning">
        <h2 id="lockout-warning">Portée de l'opération</h2>
        <p>Cette action efface simultanément les trois compteurs :</p>
        <ul>
            <li>le compteur de l'adresse e-mail normalisée ;</li>
            <li>le compteur partagé de l'adresse IP ;</li>
            <li>le compteur du couple adresse IP + e-mail.</li>
        </ul>
        <p><strong>Attention :</strong> effacer le compteur IP partagé déverrouille cette adresse IP pour tous les comptes qui l'utilisent. Le motif est obligatoire et l'action est inscrite au journal d'audit.</p>
    </section>

    <section class="card" aria-labelledby="emergency-lock-heading">
        <h2 id="emergency-lock-heading">Verrouillage d’urgence d’un compte</h2>
        <p>Le verrouillage bloque immédiatement toute nouvelle authentification et révoque toutes les sessions web et clés API actives. Le compte reste bloqué jusqu’à sa levée explicite par le super administrateur racine.</p>
        <form method="POST" action="{{ route('root.backoffice.security.authentication-lockouts.emergency-lock') }}">
            @csrf
            <label>Adresse e-mail du compte
                <input type="email" name="email" value="{{ old('email') }}" maxlength="255" required autocomplete="off">
                @error('email')<span class="error">{{ $message }}</span>@enderror
            </label>
            <label class="field" style="margin-top:18px">Motif du verrouillage
                <textarea name="reason" rows="3" minlength="10" maxlength="500" required>{{ old('reason') }}</textarea>
                @error('reason')<span class="error">{{ $message }}</span>@enderror
            </label>
            <div class="actions" style="margin-top:18px">
                <button class="button button-danger" type="submit" onclick="return confirm('Verrouiller ce compte et révoquer immédiatement toutes ses sessions et clés API ?')">Verrouiller immédiatement</button>
            </div>
        </form>
    </section>

    <section class="card" aria-labelledby="emergency-unlock-heading">
        <h2 id="emergency-unlock-heading">Lever un verrouillage d’urgence</h2>
        <p>La levée autorise une nouvelle authentification, mais ne restaure aucune ancienne session ni clé API.</p>
        <form method="POST" action="{{ route('root.backoffice.security.authentication-lockouts.emergency-unlock') }}">
            @csrf
            <label>Adresse e-mail du compte
                <input type="email" name="email" value="{{ old('email') }}" maxlength="255" required autocomplete="off">
            </label>
            <label class="field" style="margin-top:18px">Motif de la levée
                <textarea name="reason" rows="3" minlength="10" maxlength="500" required></textarea>
            </label>
            <div class="actions" style="margin-top:18px">
                <button class="button" type="submit" onclick="return confirm('Lever le verrouillage d’urgence de ce compte ?')">Lever le verrouillage</button>
            </div>
        </form>
    </section>

    <section class="card" aria-labelledby="lockout-form-heading">
        <h2 id="lockout-form-heading">Lever les blocages</h2>
        <form method="POST" action="{{ route('root.backoffice.security.authentication-lockouts.unlock') }}">
            @csrf
            <div class="form-grid">
                <label>
                    Adresse e-mail du compte
                    <input type="email" name="email" value="{{ old('email') }}" maxlength="255" required autocomplete="off">
                    @error('email')<span class="error">{{ $message }}</span>@enderror
                </label>
                <label>
                    Adresse IP concernée
                    <input type="text" name="ip_address" value="{{ old('ip_address') }}" required inputmode="text" placeholder="Ex. : 203.0.113.10">
                    @error('ip_address')<span class="error">{{ $message }}</span>@enderror
                </label>
            </div>
            <label class="field" style="margin-top:18px">
                Motif administratif
                <textarea name="reason" rows="4" minlength="10" maxlength="500" required>{{ old('reason') }}</textarea>
                @error('reason')<span class="error">{{ $message }}</span>@enderror
            </label>
            <div class="actions" style="margin-top:18px">
                <button class="button button-danger" type="submit" onclick="return confirm('Confirmer le déverrouillage du compte, de cette IP partagée et du couple IP + e-mail ?')">Confirmer le déverrouillage</button>
            </div>
        </form>
    </section>
@endsection
