@extends('layouts.app')

@section('content')
    <section class="directory-hero">
        <p class="eyebrow">Kişiler</p>
        <h1>Sanatçılar</h1>
        <p>Seslendirme sanatçıları ve ekip üyelerini tek yerde keşfet.</p>
    </section>

    <section class="directory-grid">
        @foreach($people as $person)
            <a class="directory-card" href="{{ route('people.show', $person['slug']) }}">
                <span class="directory-avatar">
                    @if($person['image'])
                        <img src="{{ $person['image'] }}" alt="">
                    @else
                        {{ mb_substr($person['name'], 0, 1) }}
                    @endif
                </span>
                <strong>{{ $person['name'] }}</strong>
                <small>{{ $person['count'] }} çalışma</small>
            </a>
        @endforeach
    </section>
@endsection
