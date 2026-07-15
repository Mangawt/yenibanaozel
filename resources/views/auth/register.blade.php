@extends('layouts.app')

@section('content')
    <section class="auth-panel">
        <p class="eyebrow">nozu.me hesabı</p>
        <h1>Kayıt ol</h1>
        <form method="post" action="{{ route('register.store') }}">
            @csrf
            <label>Ad<input name="name" value="{{ old('name') }}" required></label>
            <label>Kullanıcı adı<input name="username" value="{{ old('username') }}" required></label>
            <label>E-posta<input name="email" type="email" value="{{ old('email') }}" required></label>
            <label>Parola<input name="password" type="password" required></label>
            <label>Parola tekrar<input name="password_confirmation" type="password" required></label>
            <button class="button primary">Hesap oluştur</button>
        </form>
    </section>
@endsection
