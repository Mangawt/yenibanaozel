@extends('layouts.admin')

@section('title', 'Nozu CMS Giriş')

@section('content')
    <section class="admin-login">
        <div class="login-copy">
            <span class="eyebrow">nozu.me CMS</span>
            <h1>Yönetim paneli</h1>
            <p>İçerik importu, kuyruk takibi ve site ayarları için güvenli giriş.</p>
        </div>

        <form class="auth-card" method="post" action="{{ route('admin.authenticate') }}">
            @csrf
            <label>E-posta</label>
            <input type="email" name="email" value="{{ old('email') }}" autocomplete="email" required autofocus>

            <label>Parola</label>
            <input type="password" name="password" autocomplete="current-password" required>

            <label class="check">
                <input type="checkbox" name="remember" value="1">
                Beni hatırla
            </label>

            <button class="button primary">Giriş yap</button>
        </form>
    </section>
@endsection
