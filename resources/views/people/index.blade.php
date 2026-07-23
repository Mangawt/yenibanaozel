@extends('layouts.app')

@section('content')
    @php($total = method_exists($people, 'total') ? $people->total() : $people->count())

    <section class="directory-hero nozu-directory-hero">
        <p class="eyebrow">Kişiler</p>
        <h1>Sanatçılar</h1>
        <p>Seslendirme sanatçıları ve ekip üyelerini tek yerde keşfet.</p>
        <strong class="directory-count">{{ number_format($total, 0, ',', '.') }} sanatçı</strong>
    </section>

    <section class="directory-grid nozu-directory-grid">
        @foreach($people as $person)
            @php
                $name = is_array($person) ? $person['name'] : $person->name;
                $slug = is_array($person) ? $person['slug'] : $person->slug;
                $image = is_array($person) ? ($person['image'] ?? null) : $person->image;
                $count = is_array($person) ? $person['count'] : $person->credits_count;
            @endphp
            <a class="directory-card nozu-directory-card" href="{{ route('people.show', $slug) }}">
                <span class="directory-avatar">
                    @if($image)
                        <x-responsive-image :src="$image" alt="" sizes="72px" :widths="[96, 160]" />
                    @else
                        {{ mb_substr($name, 0, 1) }}
                    @endif
                </span>
                <span>
                    <strong>{{ $name }}</strong>
                    <small><i class="fa-solid fa-microphone-lines"></i> {{ $count }} çalışma</small>
                </span>
            </a>
        @endforeach
    </section>

    @if(method_exists($people, 'links'))
        {{ $people->links() }}
    @endif
@endsection
