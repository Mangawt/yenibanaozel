@extends('layouts.app')

@section('content')
    <section class="auth-panel">
        <p class="eyebrow">nozu.me hesabı</p>

        <h1>Kayıt ol</h1>

        <a
            class="google-auth-button"
            href="{{ route('auth.google.redirect') }}"
        >
            <svg
                width="20"
                height="20"
                viewBox="0 0 24 24"
                aria-hidden="true"
            >
                <path
                    fill="#4285F4"
                    d="M21.6 12.23c0-.71-.06-1.39-.18-2.05H12v3.87h5.38a4.6 4.6 0 0 1-2 3.02v2.51h3.24c1.9-1.75 2.98-4.33 2.98-7.35z"
                />
                <path
                    fill="#34A853"
                    d="M12 22c2.7 0 4.97-.9 6.62-2.42l-3.24-2.51c-.9.6-2.05.96-3.38.96-2.61 0-4.82-1.76-5.61-4.13H3.04v2.59A10 10 0 0 0 12 22z"
                />
                <path
                    fill="#FBBC05"
                    d="M6.39 13.9A6.02 6.02 0 0 1 6.08 12c0-.66.11-1.3.31-1.9V7.51H3.04A10 10 0 0 0 2 12c0 1.61.38 3.14 1.04 4.49l3.35-2.59z"
                />
                <path
                    fill="#EA4335"
                    d="M12 5.97c1.47 0 2.79.51 3.83 1.5l2.87-2.87A9.64 9.64 0 0 0 12 2a10 10 0 0 0-8.96 5.51l3.35 2.59C7.18 7.73 9.39 5.97 12 5.97z"
                />
            </svg>

            <span>Google ile kayıt ol</span>
        </a>

        <div class="auth-divider">
            <span>veya</span>
        </div>

        <form method="post" action="{{ route('register.store') }}">
            @csrf

            <label>
                Ad

                <input
                    name="name"
                    value="{{ old('name') }}"
                    autocomplete="name"
                    required
                >
            </label>

            <label>
                Kullanıcı adı

                <input
                    name="username"
                    value="{{ old('username') }}"
                    autocomplete="username"
                    required
                >
            </label>

            <label>
                E-posta

                <input
                    name="email"
                    type="email"
                    value="{{ old('email') }}"
                    autocomplete="email"
                    required
                >
            </label>

            <label>
                Parola

                <input
                    name="password"
                    type="password"
                    autocomplete="new-password"
                    required
                >
            </label>

            <label>
                Parola tekrar

                <input
                    name="password_confirmation"
                    type="password"
                    autocomplete="new-password"
                    required
                >
            </label>

            <button class="button primary">
                Hesap oluştur
            </button>
        </form>
    </section>
@endsection
