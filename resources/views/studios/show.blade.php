@extends('layouts.app')

@section('content')
    <section class="directory-hero">
        <p class="eyebrow">Stüdyo</p>
        <h1>{{ $studio['name'] }}</h1>
        <p>Bu stüdyo veya yapımcıyla ilişkili kayıtlar.</p>
    </section>

    <div class="poster-grid">
        @foreach($items as $item)
            @include('components.media-card', ['item' => $item])
        @endforeach
    </div>
@endsection
