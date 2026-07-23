@extends('layouts.app')

@section('content')
    @php($total = method_exists($studios, 'total') ? $studios->total() : $studios->count())

    <section class="directory-hero nozu-directory-hero">
        <p class="eyebrow">Yapım</p>
        <h1>Stüdyolar</h1>
        <p>Anime ve manga kayıtlarında geçen stüdyo ve yapımcıları görüntüle.</p>
        <strong class="directory-count">{{ number_format($total, 0, ',', '.') }} stüdyo</strong>
    </section>

    <section class="directory-grid nozu-directory-grid">
        @foreach($studios as $studio)
            @php
                $name = is_array($studio) ? $studio['name'] : $studio->name;
                $slug = is_array($studio) ? $studio['slug'] : $studio->slug;
                $image = is_array($studio) ? ($studio['sample'] ?? null) : $studio->image;
                $count = is_array($studio) ? $studio['count'] : $studio->media_count;
            @endphp
            <a class="directory-card nozu-directory-card" href="{{ route('studios.show', $slug) }}">
                <span class="directory-avatar posterish">
                    @if($image)
                        <x-responsive-image :src="$image" alt="" sizes="72px" :widths="[96, 160]" />
                    @else
                        {{ mb_substr($name, 0, 1) }}
                    @endif
                </span>
                <span>
                    <strong>{{ $name }}</strong>
                    <small><i class="fa-solid fa-clapperboard"></i> {{ $count }} içerik</small>
                </span>
            </a>
        @endforeach
    </section>

    @if(method_exists($studios, 'links'))
        {{ $studios->links() }}
    @endif
@endsection
