@extends('layouts.customer')

@section('title', 'Mon profil')

@section('content')
    <section class="card">
        <h1>Mon profil</h1>
        <p class="muted">Gérez vos informations personnelles dans votre espace client.</p>

        @if ($errors->any())
            <div class="alert alert-error" role="alert">
                <strong>Veuillez vérifier les informations saisies.</strong>
                <ul>
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form method="POST" action="{{ route('customer.profile.update') }}">
            @csrf
            @method('PUT')

            <div class="form-field">
                <label for="first_name">Prénom</label>
                <input
                    id="first_name"
                    name="first_name"
                    type="text"
                    value="{{ old('first_name', $user->first_name) }}"
                    maxlength="100"
                    autocomplete="given-name"
                    required
                >
                @error('first_name')
                    <p class="field-error">{{ $message }}</p>
                @enderror
            </div>

            <div class="form-field">
                <label for="last_name">Nom</label>
                <input
                    id="last_name"
                    name="last_name"
                    type="text"
                    value="{{ old('last_name', $user->last_name) }}"
                    maxlength="100"
                    autocomplete="family-name"
                    required
                >
                @error('last_name')
                    <p class="field-error">{{ $message }}</p>
                @enderror
            </div>

            <div class="form-field">
                <label for="telephone">Téléphone</label>
                <input
                    id="telephone"
                    name="telephone"
                    type="tel"
                    value="{{ old('telephone', $user->telephone) }}"
                    maxlength="30"
                    autocomplete="tel"
                    required
                >
                @error('telephone')
                    <p class="field-error">{{ $message }}</p>
                @enderror
            </div>

            <div class="form-field">
                <label for="email">Adresse e-mail</label>
                <input id="email" type="email" value="{{ $user->email }}" readonly aria-describedby="email-help">
                <p id="email-help" class="muted">
                    Le changement d'adresse e-mail sera effectué par une procédure de vérification sécurisée.
                </p>
            </div>

            <button type="submit">Enregistrer les modifications</button>
        </form>
    </section>

    <section class="card">
        <h2>Photo de profil</h2>
        <p class="muted">
            L'ajout et le remplacement de la photo seront activés avec le domaine Media.
            La limite validée est de 2 Mo.
        </p>
    </section>
@endsection

@push('styles')
    <style>
        .form-field{margin:18px 0}
        .form-field label{display:block;margin-bottom:7px;font-weight:600}
        .form-field input{width:100%;box-sizing:border-box;padding:12px;border:1px solid #cbd7d0;border-radius:7px;font:inherit}
        .form-field input[readonly]{background:#f4f7f5;color:#65736e}
        .field-error{color:#982d35;font-size:13px}
        button[type=submit]{padding:12px 18px;border:0;border-radius:7px;background:#176b57;color:#fff;font-weight:700;cursor:pointer}
    </style>
@endpush
