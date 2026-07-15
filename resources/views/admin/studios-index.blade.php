@extends('layouts.admin')

@section('title', 'Stüdyolar - Nozu CMS')

@section('content')
    <section class="admin-shell">
        @include('admin.partials.sidebar')

        <div class="admin-main">
            <section class="admin-hero">
                <div>
                    <p class="eyebrow">Yapim</p>
                    <h1>Stüdyolar</h1>
                    <p>Stüdyo ve yapımcı listesi import edilen medya kayıtlarından otomatik üretilir.</p>
                </div>
                <span class="queue-live">{{ number_format($studios->total(), 0, ',', '.') }} studyo</span>
            </section>

            <section class="panel">
                <h2>Arama</h2>
                <form class="filters" method="get" action="{{ route('admin.studios.index') }}">
                    <input type="search" name="q" value="{{ request('q') }}" placeholder="Studyo ara">
                    <button class="button primary">Ara</button>
                </form>
            </section>

            <section class="directory-admin-grid">
                @foreach($studios as $studio)
                    <article class="directory-admin-card">
                        <span class="directory-admin-avatar posterish">
                            @if($studio->image)
                                <img src="{{ $studio->image }}" alt="">
                            @else
                                {{ mb_substr($studio->name, 0, 1) }}
                            @endif
                        </span>
                        <div>
                            <strong>{{ $studio->name }}</strong>
                            <small>{{ $studio->media_count }} içerik</small>
                        </div>
                        <a class="button" href="{{ route('studios.show', $studio->slug) }}" target="_blank" rel="noopener">Önizle</a>
                    </article>
                @endforeach
            </section>
            {{ $studios->links() }}
        </div>
    </section>
@endsection
