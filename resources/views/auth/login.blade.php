@extends('layouts.app')

@section('content')
    <section class="auth-panel">
        <p class="eyebrow">nozu.me hesabı</p>
        <h1>Giriş yap</h1>
        <form method="post" action="{{ route('login.authenticate') }}">
            @csrf
            <label>E-posta<input name="email" type="email" value="{{ old('email') }}" required></label>
            <label>Parola<input name="password" type="password" required></label>
            <label class="check"><input type="checkbox" name="remember" value="1"> Beni hatırla</label>
            <button class="button primary">Giriş yap</button>
        </form>
        <p class="muted">Hesabın yoksa <a href="{{ route('register') }}">kayıt ol</a>.</p>
    </section>
@endsection
