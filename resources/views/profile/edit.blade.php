@extends('layouts.app')

@section('content')
    <section class="profile-shell">
        <aside class="profile-card">
            <div class="profile-avatar">
                @if($user->avatar_path)
                    <img src="{{ asset('storage/'.$user->avatar_path) }}" alt="{{ $user->name }}">
                @else
                    <span>{{ mb_substr($user->name, 0, 1) }}</span>
                @endif
            </div>
            <h1>{{ $user->name }}</h1>
            <p>{{ '@'.$user->username }}</p>
            <a class="button" href="{{ route('profile.show', $user->username) }}">Public profili gör</a>
        </aside>

        <form class="profile-form" method="post" action="{{ route('profile.update') }}" enctype="multipart/form-data">
            @csrf
            <h2>Profil ayarları</h2>
            <label>Ad<input name="name" value="{{ old('name', $user->name) }}" required></label>
            <label>Kullanıcı adı<input name="username" value="{{ old('username', $user->username) }}" required></label>
            <label>Hakkımda<textarea name="bio" rows="5">{{ old('bio', $user->bio) }}</textarea></label>
            <label>Tema
                <select name="theme">
                    <option value="dark" @selected($user->theme === 'dark')>Karanlık</option>
                    <option value="light" @selected($user->theme === 'light')>Aydınlık</option>
                    <option value="system" @selected($user->theme === 'system')>Sistem</option>
                </select>
            </label>
            <label>Avatar<input type="file" name="avatar" accept="image/*"></label>
            <button class="button primary">Kaydet</button>
        </form>
    </section>
@endsection
