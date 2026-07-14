@extends('layouts.app', ['settings' => ['site_name' => 'nozu.me']])

@section('content')
    <section class="auth-card">
        <h1>Admin girişi</h1>
        <form method="post" action="{{ route('admin.authenticate') }}">
            @csrf
            <label>Şifre</label>
            <input type="password" name="password" required autofocus>
            @error('password')<p class="error">{{ $message }}</p>@enderror
            <button class="button primary">Giriş yap</button>
        </form>
    </section>
@endsection
