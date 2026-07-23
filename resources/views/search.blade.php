@extends('layouts.app')

@section('content')
    <section class="filter-hero compact">
        <form class="ani-filter" action="{{ route('search') }}" method="get">
            <label>
                <span>Arama</span>
                <input class="js-autocomplete" type="search" name="q" value="{{ $query }}" placeholder="Başlık ara" autocomplete="off">
            </label>
            <label>
                <span>Tip</span>
                <select name="type">
                    <option value="">Tümü</option>
                    <option value="anime" @selected($type === 'anime')>Anime</option>
                    <option value="manga" @selected($type === 'manga')>Manga</option>
                </select>
            </label>
            <label>
                <span>Türler</span>
                <select name="genre">
                    <option value="">Tümü</option>
                    @foreach($genres as $label)
                        <option value="{{ $label }}" @selected($genre === $label)>{{ $label }}</option>
                    @endforeach
                </select>
            </label>
            <label>
                <span>Yıl</span>
                <select name="year">
                    <option value="">Tümü</option>
                    @for($optionYear = now()->year + 1; $optionYear >= 1980; $optionYear--)
                        <option value="{{ $optionYear }}" @selected((string) $optionYear === (string) $year)>{{ $optionYear }}</option>
                    @endfor
                </select>
            </label>
            <label>
                <span>Sezon</span>
                <select name="season">
                    <option value="">Tümü</option>
                    @foreach($seasons as $label)
                        <option value="{{ $label }}" @selected($season === $label)>{{ $label }}</option>
                    @endforeach
                </select>
            </label>
            <label>
                <span>Biçim</span>
                <select name="format">
                    <option value="">Tümü</option>
                    @foreach($formats as $label)
                        <option value="{{ $label }}" @selected($format === $label)>{{ $label }}</option>
                    @endforeach
                </select>
            </label>
            <button class="filter-button" aria-label="Filtrele"><i class="fa-solid fa-sliders"></i></button>
        </form>
    </section>

    @php
        $total = method_exists($items, 'total') ? $items->total() : $items->count();
        $typeLabel = $type === 'anime' ? 'anime' : ($type === 'manga' ? 'manga' : 'sonuç');
    @endphp

    <x-section-title title="Sonuçlar" />
    <p class="result-count">{{ number_format($total, 0, ',', '.') }} {{ $typeLabel }}</p>

    <div class="poster-grid">
        @forelse($items as $item)
            @include('components.media-card', ['item' => $item])
        @empty
            <p class="empty">Sonuç bulunamadı.</p>
        @endforelse
    </div>

    <div class="pagination-wrap">{{ $items->links() }}</div>
@endsection
