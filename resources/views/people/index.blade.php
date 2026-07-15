@extends('layouts.app')

@section('content')
    <section class="directory-hero">
        <p class="eyebrow">Kişiler</p>
        <h1>Sanatçılar</h1>
        <p>Seslendirme sanatçıları ve ekip üyelerini tek yerde keşfet.</p>
    </section>

    <section class="directory-grid">
        @foreach($people as $person)
            @php
                $name = is_array($person) ? $person['name'] : $person->name;
                $slug = is_array($person) ? $person['slug'] : $person->slug;
                $image = is_array($person) ? ($person['image'] ?? null) : $person->image;
                $count = is_array($person) ? $person['count'] : $person->credits_count;
            @endphp
            <a class="directory-card" href="{{ route('people.show', $slug) }}">
                <span class="directory-avatar">
                    @if($image)
                        <img src="{{ $image }}" alt="">
                    @else
                        {{ mb_substr($name, 0, 1) }}
                    @endif
                </span>
                <strong>{{ $name }}</strong>
                <small>{{ $count }} çalışma</small>
            </a>
        @endforeach
    </section>

    @if(method_exists($people, 'links'))
        {{ $people->links() }}
    @endif
@endsection
