@extends('layouts.app')

@section('content')
    <section class="directory-hero nozu-directory-hero">
        <p class="eyebrow">Stüdyo</p>
        <h1>{{ $studio['name'] }}</h1>
        <p>Bu stüdyo veya yapımcıyla ilişkili kayıtlar.</p>
    </section>

    <div class="poster-grid nozu-studio-media-grid">
        @foreach($items as $item)
            @include('components.media-card', ['item' => $item])
        @endforeach
    </div>
    @if(method_exists($items, 'links'))
        {{ $items->links() }}
    @endif
@endsection
