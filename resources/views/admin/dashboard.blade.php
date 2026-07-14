@extends('layouts.app')

@section('content')
    <section class="admin-bar">
        <div>
            <h1>Admin paneli</h1>
            <p>{{ $mediaCount }} içerik · {{ $animeCount }} anime · {{ $mangaCount }} manga</p>
        </div>
        <div class="actions">
            <a class="button" href="{{ route('admin.settings') }}">Ayarlar</a>
            <form method="post" action="{{ route('admin.logout') }}">@csrf<button class="button">Çıkış</button></form>
        </div>
    </section>

    <section class="panel">
        <h2>AniList aramasıyla tekil içerik ekle</h2>
        <form class="filters" method="get" action="{{ route('admin.dashboard') }}">
            <select name="type">
                <option value="anime" @selected(request('type') === 'anime')>Anime</option>
                <option value="manga" @selected(request('type') === 'manga')>Manga</option>
            </select>
            <input type="search" name="q" value="{{ request('q') }}" placeholder="Başlık yaz">
            <button class="button primary">Ara</button>
        </form>
    </section>

    <section class="panel">
        <h2>AniList API’den toplu çek</h2>
        <form class="filters bulk-form" method="post" action="{{ route('admin.bulk-import') }}">
            @csrf
            <select name="type">
                <option value="anime">Anime</option>
                <option value="manga">Manga</option>
            </select>
            <select name="sort">
                <option value="POPULARITY_DESC">Popüler</option>
                <option value="TRENDING_DESC">Trend</option>
                <option value="SCORE_DESC">Yüksek puan</option>
                <option value="START_DATE_DESC">Yeni çıkan</option>
            </select>
            <input type="search" name="q" placeholder="İsteğe bağlı arama">
            <input type="number" name="per_page" min="1" max="25" value="10">
            <button class="button primary">Toplu aktar</button>
        </form>
    </section>

    <div class="admin-results">
        @foreach($results as $result)
            <article class="result-row">
                @if($result['cover_image'])
                    <img src="{{ $result['cover_image'] }}" alt="">
                @endif
                <div>
                    <strong>{{ $result['title'] }}</strong>
                    <p>AniList · {{ $result['format'] }} @if($result['average_score']) · {{ $result['average_score'] }}/100 @endif</p>
                </div>
                <form method="post" action="{{ route('admin.import') }}">
                    @csrf
                    <input type="hidden" name="type" value="{{ request('type', 'anime') }}">
                    <input type="hidden" name="id" value="{{ $result['id'] }}">
                    <button class="button primary">Siteye ekle</button>
                </form>
            </article>
        @endforeach
    </div>
@endsection
