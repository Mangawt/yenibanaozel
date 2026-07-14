@extends('layouts.app')

@section('content')
    <section class="filter-hero">
        <form class="ani-filter" action="{{ route('search') }}" method="get">
            <label>
                <span>Search</span>
                <input type="search" name="q" placeholder="Anime veya manga ara">
            </label>
            <label>
                <span>Genres</span>
                <select name="genre">
                    <option value="">Any</option>
                    @foreach($genres as $label)
                        <option value="{{ $label }}">{{ $label }}</option>
                    @endforeach
                </select>
            </label>
            <label>
                <span>Year</span>
                <select name="year">
                    <option value="">Any</option>
                    @for($year = now()->year + 1; $year >= 1980; $year--)
                        <option value="{{ $year }}">{{ $year }}</option>
                    @endfor
                </select>
            </label>
            <label>
                <span>Season</span>
                <select name="season">
                    <option value="">Any</option>
                    @foreach($seasons as $label)
                        <option value="{{ $label }}">{{ $label }}</option>
                    @endforeach
                </select>
            </label>
            <label>
                <span>Format</span>
                <select name="format">
                    <option value="">Any</option>
                    @foreach($formats as $label)
                        <option value="{{ $label }}">{{ $label }}</option>
                    @endforeach
                </select>
            </label>
            <button class="filter-button" aria-label="Filtrele">☰</button>
        </form>
    </section>

    <x-section-title title="TRENDING NOW" />
    <div class="poster-grid">
        @forelse($trending as $item)
            @include('components.media-card', ['item' => $item])
        @empty
            <p class="empty">Henüz içerik yok. Admin panelinden toplu içerik çekebilirsin.</p>
        @endforelse
    </div>

    <x-section-title title="EN YÜKSEK PUANLI ANİMELER" />
    <div class="poster-grid">
        @foreach($topAnime as $item)
            @include('components.media-card', ['item' => $item])
        @endforeach
    </div>

    <x-section-title title="EN YÜKSEK PUANLI MANGALAR" />
    <div class="poster-grid">
        @foreach($topManga as $item)
            @include('components.media-card', ['item' => $item])
        @endforeach
    </div>
@endsection
