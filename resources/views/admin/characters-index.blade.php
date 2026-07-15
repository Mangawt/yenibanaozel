@extends('layouts.admin')

@section('title', 'Karakterler - Nozu CMS')

@section('content')
    <section class="admin-shell">
        @include('admin.partials.sidebar')

        <div class="admin-main">
            <section class="admin-hero">
                <div>
                    <p class="eyebrow">Karakterler</p>
                    <h1>Karakterler</h1>
                    <p>Import edilen anime ve manga karakterleri normalize tablodan listelenir.</p>
                </div>
                <span class="queue-live">{{ number_format($characters->total(), 0, ',', '.') }} karakter</span>
            </section>

            <section class="panel">
                <h2>Arama</h2>
                <form class="filters" method="get" action="{{ route('admin.characters.index') }}">
                    <input type="search" name="q" value="{{ request('q') }}" placeholder="Karakter ara">
                    <button class="button primary">Ara</button>
                </form>
            </section>

            <section class="directory-admin-grid">
                @foreach($characters as $character)
                    <article class="directory-admin-card">
                        <span class="directory-admin-avatar">
                            @if($character->image)
                                <img src="{{ $character->image }}" alt="">
                            @else
                                {{ mb_substr($character->name, 0, 1) }}
                            @endif
                        </span>
                        <div>
                            <strong>{{ $character->name }}</strong>
                            <small>{{ $character->media_count }} içerik</small>
                        </div>
                    </article>
                @endforeach
            </section>
            {{ $characters->links() }}
        </div>
    </section>
@endsection
