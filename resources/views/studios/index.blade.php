@extends('layouts.app')

@section('content')
    <section class="directory-hero">
        <p class="eyebrow">Yapım</p>
        <h1>Stüdyolar</h1>
        <p>Anime ve manga kayıtlarında geçen stüdyo ve yapımcıları görüntüle.</p>
    </section>

    <section class="directory-grid">
        @foreach($studios as $studio)
            <a class="directory-card" href="{{ route('studios.show', $studio['slug']) }}">
                <span class="directory-avatar posterish">
                    @if($studio['sample'])
                        <img src="{{ $studio['sample'] }}" alt="">
                    @else
                        {{ mb_substr($studio['name'], 0, 1) }}
                    @endif
                </span>
                <strong>{{ $studio['name'] }}</strong>
                <small>{{ $studio['count'] }} içerik</small>
            </a>
        @endforeach
    </section>
@endsection
