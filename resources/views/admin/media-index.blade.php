@extends('layouts.admin')

@section('title', ucfirst($type).' - Nozu CMS')

@section('content')
    <section class="admin-shell">
        @include('admin.partials.sidebar')

        <div class="admin-main">
            <section class="admin-hero">
                <div>
                    <p class="eyebrow">İçerik Yönetimi</p>
                    <h1>{{ $type === 'anime' ? 'Anime' : 'Manga' }}</h1>
                    <p>Kayıtları ara, filtrele, düzenle ve public sayfayı önizle.</p>
                </div>
                <span class="queue-live">{{ number_format($count, 0, ',', '.') }} kayıt</span>
            </section>

            <section class="panel">
                <h2>Filtreler</h2>
                <form class="filters" method="get" action="{{ $type === 'manga' ? route('admin.manga.index') : route('admin.anime.index') }}">
                    <input type="search" name="q" value="{{ request('q') }}" placeholder="Başlık ara">
                    <input name="status" value="{{ request('status') }}" placeholder="Durum">
                    <input name="format" value="{{ request('format') }}" placeholder="Format">
                    <input type="number" name="year" value="{{ request('year') }}" placeholder="Yıl">
                    <select name="sort">
                        <option value="newest" @selected(request('sort', 'newest') === 'newest')>En yeni</option>
                        <option value="oldest" @selected(request('sort') === 'oldest')>En eski</option>
                        <option value="title" @selected(request('sort') === 'title')>Başlık</option>
                        <option value="popularity" @selected(request('sort') === 'popularity')>Populerlik</option>
                        <option value="score" @selected(request('sort') === 'score')>Puan</option>
                        <option value="updated" @selected(request('sort') === 'updated')>Güncellenme</option>
                    </select>
                    <button class="button primary">Filtrele</button>
                </form>
            </section>

            <section class="panel">
                <h2>Kayitlar</h2>
                <div class="admin-table">
                    @foreach($items as $item)
                        <article class="media-admin-row">
                            <a class="thumb" href="{{ route('media.show', ['type' => $item->type, 'media' => $item]) }}" target="_blank" rel="noopener">
                                @if($item->cover_image)<img src="{{ $item->cover_image }}" alt="">@endif
                            </a>
                            <div class="media-admin-copy">
                                <strong>{{ $item->title }}</strong>
                                <span>{{ $item->format ?: '-' }} / {{ $item->status ?: '-' }} / {{ $item->start_year ?: '-' }}</span>
                                <small>{{ collect($item->genres ?? [])->take(5)->implode(', ') }}</small>
                            </div>
                            <div class="row-metrics">
                                <span>Puan <strong>{{ $item->average_score ?: '-' }}</strong></span>
                                <span>Pop <strong>{{ $item->popularity ? number_format($item->popularity, 0, ',', '.') : '-' }}</strong></span>
                            </div>
                            <div class="row-actions">
                                <a class="button" href="{{ route('media.show', ['type' => $item->type, 'media' => $item]) }}" target="_blank" rel="noopener">Önizle</a>
                                <a class="button primary" href="{{ route('admin.media.edit', $item) }}">Düzenle</a>
                            </div>
                        </article>
                    @endforeach
                </div>
                {{ $items->links() }}
            </section>
        </div>
    </section>
@endsection
