@extends('layouts.app')

@section('content')
    <section class="profile-shell">
        <aside class="profile-card">
            <div class="profile-avatar">
                @if($user->avatar_path)
                    <img src="{{ asset('storage/'.$user->avatar_path) }}" alt="{{ $user->username }}">
                @else
                    <span>{{ mb_substr($user->username ?: 'N', 0, 1) }}</span>
                @endif
            </div>
            <h1>{{ '@'.$user->username }}</h1>
            <a class="button" href="{{ route('profile.show', $user->username) }}">Profili gör</a>
        </aside>

        <form class="profile-form" method="post" action="{{ route('profile.update') }}" enctype="multipart/form-data">
            @csrf
            <h2>Profil ayarları</h2>

            @if(in_array($user->role, ['admin', 'super_admin'], true))
                <label>Kullanıcı adı<input name="username" value="{{ old('username', $user->username) }}" required></label>
            @else
                <label>Kullanıcı adı<input value="{{ $user->username }}" disabled></label>
            @endif

            <label>Hakkımda<textarea name="bio" rows="5">{{ old('bio', $user->bio) }}</textarea></label>
            <div class="profile-social-fields">
                <h3>Sosyal bağlantılar</h3>
                <label>Instagram<input name="social_links[instagram]" value="{{ old('social_links.instagram', $user->social_links['instagram'] ?? '') }}" placeholder="https://instagram.com/kullanici"></label>
                <label>Facebook<input name="social_links[facebook]" value="{{ old('social_links.facebook', $user->social_links['facebook'] ?? '') }}" placeholder="https://facebook.com/kullanici"></label>
                <label>Discord<input name="social_links[discord]" value="{{ old('social_links.discord', $user->social_links['discord'] ?? '') }}" placeholder="kullanici#0000 veya davet linki"></label>
                <label>X / Twitter<input name="social_links[x]" value="{{ old('social_links.x', $user->social_links['x'] ?? '') }}" placeholder="https://x.com/kullanici"></label>
                <label>YouTube<input name="social_links[youtube]" value="{{ old('social_links.youtube', $user->social_links['youtube'] ?? '') }}" placeholder="https://youtube.com/@kanal"></label>
                <label>Web sitesi<input name="social_links[website]" value="{{ old('social_links.website', $user->social_links['website'] ?? '') }}" placeholder="https://site.com"></label>
            </div>
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
