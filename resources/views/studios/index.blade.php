@extends('layouts.app')

@section('content')
    <section class="directory-hero">
        <p class="eyebrow">Yapım</p>
        <h1>Stüdyolar</h1>
        <p>Anime ve manga kayıtlarında geçen stüdyo ve yapımcıları görüntüle.</p>
    </section>

    <section class="directory-grid">
        @foreach($studios as $studio)
            @php
                $name = is_array($studio) ? $studio['name'] : $studio->name;
                $slug = is_array($studio) ? $studio['slug'] : $studio->slug;
                $image = is_array($studio) ? ($studio['sample'] ?? null) : $studio->image;
                $count = is_array($studio) ? $studio['count'] : $studio->media_count;
            @endphp
            <a class="directory-card" href="{{ route('studios.show', $slug) }}">
                <span class="directory-avatar posterish">
                    @if($image)
                        <img src="{{ $image }}" alt="">
                    @else
                        {{ mb_substr($name, 0, 1) }}
                    @endif
                </span>
                <strong>{{ $name }}</strong>
                <small>{{ $count }} içerik</small>
            </a>
        @endforeach
    </section>

    @if(method_exists($studios, 'links'))
        {{ $studios->links() }}
    @endif
@endsection
