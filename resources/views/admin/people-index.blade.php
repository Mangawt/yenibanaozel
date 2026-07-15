@extends('layouts.admin')

@section('title', 'Sanatçılar - Nozu CMS')

@section('content')
    <section class="admin-shell">
        @include('admin.partials.sidebar')

        <div class="admin-main">
            <section class="admin-hero">
                <div>
                    <p class="eyebrow">Kişiler</p>
                    <h1>Sanatçılar</h1>
                    <p>Seslendirme sanatcilari ve ekip uyeleri. Bu liste import edilen medya verilerinden otomatik uretilir.</p>
                </div>
                <span class="queue-live">{{ number_format($people->total(), 0, ',', '.') }} kisi</span>
            </section>

            <section class="panel">
                <h2>Arama</h2>
                <form class="filters" method="get" action="{{ route('admin.people.index') }}">
                    <input type="search" name="q" value="{{ request('q') }}" placeholder="Kisi ara">
                    <button class="button primary">Ara</button>
                </form>
            </section>

            <section class="directory-admin-grid">
                @foreach($people as $person)
                    <article class="directory-admin-card">
                        <span class="directory-admin-avatar">
                            @if($person->image)
                                <img src="{{ $person->image }}" alt="">
                            @else
                                {{ mb_substr($person->name, 0, 1) }}
                            @endif
                        </span>
                        <div>
                            <strong>{{ $person->name }}</strong>
                            <small>{{ $person->credits_count }} kredi</small>
                        </div>
                        <a class="button" href="{{ route('people.show', $person->slug) }}" target="_blank" rel="noopener">Önizle</a>
                    </article>
                @endforeach
            </section>
            {{ $people->links() }}
        </div>
    </section>
@endsection
