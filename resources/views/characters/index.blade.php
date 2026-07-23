@extends('layouts.app')

@section('content')
    @php($total = method_exists($characters, 'total') ? $characters->total() : $characters->count())

    <section class="directory-hero nozu-directory-hero">
        <p class="eyebrow">Karakterler</p>
        <h1>Anime ve Manga Karakterleri</h1>
        <p>Arşivdeki karakterleri, görselleri ve hangi serilerde yer aldıklarını keşfet.</p>
        <strong class="directory-count">{{ number_format($total, 0, ',', '.') }} karakter</strong>
    </section>

    <section class="directory-grid nozu-directory-grid">
        @foreach($characters as $character)
            @php
                $name = is_array($character) ? $character['name'] : $character->name;
                $slug = is_array($character) ? $character['slug'] : $character->slug;
                $image = is_array($character) ? ($character['image'] ?? null) : $character->image;
                $count = is_array($character) ? $character['count'] : $character->media_count;
            @endphp
            <a class="directory-card nozu-directory-card" href="{{ route('characters.show', $slug) }}">
                <span class="directory-avatar">
                    @if($image)
                        <x-responsive-image :src="$image" alt="" sizes="72px" :widths="[96, 160]" />
                    @else
                        {{ mb_substr($name, 0, 1) }}
                    @endif
                </span>
                <span>
                    <strong>{{ $name }}</strong>
                    <small><i class="fa-solid fa-clapperboard"></i> {{ $count }} seri</small>
                </span>
            </a>
        @endforeach
    </section>

    @if(method_exists($characters, 'links'))
        {{ $characters->links() }}
    @endif
@endsection
