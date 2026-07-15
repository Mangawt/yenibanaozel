@extends('layouts.admin')

@section('title', 'Nozu CMS')

@section('content')
    <section class="admin-shell">
        @include('admin.partials.sidebar')

        <div class="admin-main">
            <section class="admin-hero">
                <div>
                    <p class="eyebrow">Yönetim Paneli</p>
                    <h1>İçerik merkezi</h1>
                    <p>Tekil içerik ekle, kuyruğu takip et ve site ayarlarını buradan yönet.</p>
                </div>
                <a class="button primary" href="{{ route('admin.import-queue') }}">Kuyruğa Git</a>
            </section>

            <section class="metric-grid">
                <article><span>Toplam İçerik</span><strong>{{ number_format($mediaCount, 0, ',', '.') }}</strong></article>
                <article><span>Anime</span><strong>{{ number_format($animeCount, 0, ',', '.') }}</strong></article>
                <article><span>Manga</span><strong>{{ number_format($mangaCount, 0, ',', '.') }}</strong></article>
                <article><span>Kuyruk</span><strong>{{ number_format($queueCount, 0, ',', '.') }}</strong></article>
                <article><span>Hatalı</span><strong>{{ number_format($failedCount, 0, ',', '.') }}</strong></article>
            </section>

            <section class="panel">
                <h2>Tekil içerik ekle</h2>
                <form class="filters" method="get" action="{{ route('admin.dashboard') }}">
                    <select name="type">
                        <option value="anime" @selected(request('type') === 'anime')>Anime</option>
                        <option value="manga" @selected(request('type') === 'manga')>Manga</option>
                    </select>
                    <input type="search" name="q" value="{{ request('q') }}" placeholder="Başlık yaz">
                    <button class="button primary">Ara</button>
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
                            <p>{{ $result['format'] }} @if($result['average_score']) - {{ $result['average_score'] }}/100 @endif</p>
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
        </div>
    </section>
@endsection
