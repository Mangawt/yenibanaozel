@extends('layouts.app')

@section('content')
    @if($heroItems->isNotEmpty())
        <section class="home-slider">
            @foreach($heroItems as $index => $item)
                <article class="slide @if($index === 0) active @endif" style="--hero:url('{{ $item->banner_image ?: $item->cover_image }}')">
                    <div class="slide-copy">
                        <span>{{ $item->type === 'anime' ? 'Anime' : 'Manga' }} · {{ $item->format }}</span>
                        <h1>{{ $item->title }}</h1>
                        <p>{{ \Illuminate\Support\Str::limit(strip_tags($item->description), 180) }}</p>
                        <a class="button primary" href="{{ route('media.show', ['type' => $item->type, 'media' => $item]) }}">Detaya git</a>
                    </div>
                </article>
            @endforeach
        </section>
    @endif

    <section class="filter-hero">
        <form class="ani-filter" action="{{ route('search') }}" method="get">
            <label>
                <span>Arama</span>
                <input class="js-autocomplete" type="search" name="q" placeholder="Anime veya manga ara" autocomplete="off">
            </label>
            <label>
                <span>Türler</span>
                <select name="genre">
                    <option value="">Tümü</option>
                    @foreach($genres as $label)
                        <option value="{{ $label }}">{{ $label }}</option>
                    @endforeach
                </select>
            </label>
            <label>
                <span>Yıl</span>
                <select name="year">
                    <option value="">Tümü</option>
                    @for($year = now()->year + 1; $year >= 1980; $year--)
                        <option value="{{ $year }}">{{ $year }}</option>
                    @endfor
                </select>
            </label>
            <label>
                <span>Sezon</span>
                <select name="season">
                    <option value="">Tümü</option>
                    @foreach($seasons as $label)
                        <option value="{{ $label }}">{{ $label }}</option>
                    @endforeach
                </select>
            </label>
            <label>
                <span>Biçim</span>
                <select name="format">
                    <option value="">Tümü</option>
                    @foreach($formats as $label)
                        <option value="{{ $label }}">{{ $label }}</option>
                    @endforeach
                </select>
            </label>
            <button class="filter-button" aria-label="Filtrele">☰</button>
        </form>
    </section>

    <x-section-title title="Şu An Trend" :href="route('search')" />
    <div class="poster-grid home-five">
        @forelse($trending as $item)
            @include('components.media-card', ['item' => $item])
        @empty
            <p class="empty">Henüz içerik yok. Admin panelinden kuyrukla içerik çekebilirsin.</p>
        @endforelse
    </div>

    <x-section-title title="Bu Sezon Popüler" :href="route('search', ['season' => request('season')])" />
    <div class="poster-grid home-five">
        @foreach($seasonPopular as $item)
            @include('components.media-card', ['item' => $item])
        @endforeach
    </div>

    <x-section-title title="Yakında Gelecekler" :href="route('search')" />
    <div class="poster-grid home-five">
        @foreach($upcoming as $item)
            @include('components.media-card', ['item' => $item])
        @endforeach
    </div>

    <x-section-title title="En Yüksek Puanlı Animeler" :href="route('search', ['type' => 'anime'])" />
    <div class="poster-grid home-five">
        @foreach($topAnime as $item)
            @include('components.media-card', ['item' => $item])
        @endforeach
    </div>

    <x-section-title title="En Yüksek Puanlı Mangalar" :href="route('search', ['type' => 'manga'])" />
    <div class="poster-grid home-five">
        @foreach($topManga as $item)
            @include('components.media-card', ['item' => $item])
        @endforeach
    </div>
@endsection
