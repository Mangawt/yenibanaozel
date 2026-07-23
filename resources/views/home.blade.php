@extends('layouts.app')

@section('content')
    <div class="nozu-discover nozu-discover-single">
        <div class="nozu-discover-main">
            <section class="nozu-search-head">
                <span class="nozu-discover-eyebrow">nozu.me keşif</span>
                <h1>Anime ve Manga Keşfet</h1>
                <form class="nozu-large-search autocomplete-wrap" action="{{ route('search') }}" method="get">
                    <i class="fa-solid fa-magnifying-glass"></i>
                    <input class="js-autocomplete" type="search" name="q" placeholder="Anime, manga veya karakter ara" autocomplete="off">
                </form>
                <div class="nozu-discover-actions">
                    <a href="{{ route('search', ['type' => 'anime']) }}"><i class="fa-solid fa-tv"></i><span>Anime</span></a>
                    <a href="{{ route('search', ['type' => 'manga']) }}"><i class="fa-solid fa-book-open"></i><span>Manga</span></a>
                    <a href="{{ route('search', ['sort' => 'score']) }}"><i class="fa-solid fa-star"></i><span>Yüksek puan</span></a>
                    <a href="{{ route('search', ['status' => 'NOT_YET_RELEASED']) }}"><i class="fa-solid fa-calendar-days"></i><span>Yakında</span></a>
                </div>
                <p>Ya da <a href="{{ route('search') }}">gelişmiş arama</a> ile tür, yıl ve sezon filtrelerini kullan.</p>
            </section>

            <section class="nozu-row-section">
                <x-section-title title="Şu An Trend" :href="route('search')" />
                <div class="poster-grid nozu-poster-strip">
                    @forelse($trending->take(6) as $item)
                        @include('components.media-card', ['item' => $item])
                    @empty
                        <p class="empty">Henüz içerik yok. Admin panelinden kuyrukla içerik çekebilirsin.</p>
                    @endforelse
                </div>
            </section>

            @if($seasonPopular->isNotEmpty())
                <section class="nozu-row-section">
                    <x-section-title title="Bu Sezon Popüler" :href="route('search', ['type' => 'anime'])" />
                    <div class="poster-grid nozu-poster-strip">
                        @foreach($seasonPopular->take(6) as $item)
                            @include('components.media-card', ['item' => $item])
                        @endforeach
                    </div>
                </section>
            @endif

            @if($upcoming->isNotEmpty())
                <section class="nozu-row-section">
                    <x-section-title title="Yakında Gelecekler" :href="route('search')" />
                    <div class="poster-grid nozu-poster-strip">
                        @foreach($upcoming->take(6) as $item)
                            @include('components.media-card', ['item' => $item])
                        @endforeach
                    </div>
                </section>
            @endif

            @if($topAnime->isNotEmpty())
                <section class="nozu-row-section">
                    <x-section-title title="En Yüksek Puanlı Anime" :href="route('search', ['type' => 'anime', 'sort' => 'score'])" />
                    <div class="poster-grid nozu-poster-strip">
                        @foreach($topAnime->take(6) as $item)
                            @include('components.media-card', ['item' => $item])
                        @endforeach
                    </div>
                </section>
            @endif

            @if($topManga->isNotEmpty())
                <section class="nozu-row-section">
                    <x-section-title title="En Popüler Manga" :href="route('search', ['type' => 'manga'])" />
                    <div class="poster-grid nozu-poster-strip">
                        @foreach($topManga->take(6) as $item)
                            @include('components.media-card', ['item' => $item])
                        @endforeach
                    </div>
                </section>
            @endif

            @if($top100Anime->isNotEmpty())
                <section class="nozu-top100-section">
                    <div class="section-title nozu-top100-title">
                        <h2>Top 100 Anime</h2>
                        <a href="{{ route('search', ['type' => 'anime', 'sort' => 'score']) }}">Devamını gör</a>
                    </div>
                    <div class="nozu-top100-list">
                        @foreach($top100Anime as $item)
                            <a class="nozu-top100-item" href="{{ route('media.show', ['type' => $item->type, 'media' => $item]) }}">
                                <span class="nozu-top100-rank">#{{ $loop->iteration }}</span>
                                @if($item->cover_image)
                                    <x-responsive-image
                                        :src="$item->cover_image"
                                        :alt="$item->title"
                                        sizes="(max-width: 760px) 74px, 64px"
                                        :widths="[96, 160]"
                                    />
                                @endif
                                <span class="nozu-top100-main">
                                    <strong>{{ $item->title }}</strong>
                                    <small>
                                        @forelse(array_slice($item->genres ?? [], 0, 3) as $genre)
                                            <em>{{ $genre }}</em>
                                        @empty
                                            <em>Anime</em>
                                        @endforelse
                                    </small>
                                </span>
                                <span class="nozu-top100-meta">
                                    <strong>{{ $item->format ?: 'Anime' }}</strong>
                                    <small>
                                        @if($item->episodes)
                                            {{ $item->episodes }} bölüm
                                        @elseif($item->duration)
                                            {{ $item->duration }} dk
                                        @else
                                            {{ ucfirst($item->type) }}
                                        @endif
                                    </small>
                                </span>
                                <span class="nozu-top100-meta">
                                    <strong>{{ trim(($item->season ?? '').' '.($item->season_year ?? '')) ?: ($item->start_year ?: '-') }}</strong>
                                    <small>{{ $item->status ?: 'Kayıtlı' }}</small>
                                </span>
                            </a>
                        @endforeach
                    </div>
                </section>
            @endif
        </div>
    </div>
@endsection
