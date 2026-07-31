@extends('layouts.app')

@section('content')
    @php
        $total = method_exists($people, 'total')
            ? $people->total()
            : $people->count();
    @endphp

    <section class="directory-hero nozu-directory-hero">
        <p class="eyebrow">Kişiler</p>
        <h1>Sanatçılar ve Sektör Çalışanları</h1>
        <p>Yazarları, çizerleri, yönetmenleri, seslendirme sanatçılarını ve diğer sektör çalışanların keşfet.</p>

        <strong class="directory-count">
            {{ number_format($total, 0, ',', '.') }} kişi
        </strong>
    </section>

    <section class="directory-grid nozu-directory-grid">
        @foreach ($people as $person)
            @php
                $name = is_array($person)
                    ? ($person['name'] ?? 'İsimsiz Kişi')
                    : ($person->name ?? 'İsimsiz Kişi');

                $slug = is_array($person)
                    ? ($person['slug'] ?? null)
                    : ($person->slug ?? null);

                $image = is_array($person)
                    ? ($person['image'] ?? null)
                    : ($person->image ?? null);

                $count = is_array($person)
                    ? ($person['count'] ?? $person['media_count'] ?? 0)
                    : ($person->media_count ?? $person->count ?? 0);

                $role = is_array($person)
                    ? ($person['role'] ?? $person['primary_role'] ?? null)
                    : ($person->role ?? $person->primary_role ?? null);
            @endphp

            @if ($slug)
                <a
                    class="directory-card nozu-directory-card"
                    href="{{ route('people.show', ['slug' => $slug]) }}"
                >
                    <span class="directory-avatar">
                        @if ($image)
                            <img
                                src="{{ $image }}"
                                alt="{{ $name }}"
                                width="72"
                                height="72"
                                loading="lazy"
                                decoding="async"
                            >
                        @else
                            {{ mb_substr($name, 0, 1) }}
                        @endif
                    </span>

                    <span>
                        <strong>{{ $name }}</strong>

                        @if ($role)
                            <small>{{ $role }}</small>
                        @else
                            <small>
                                <i class="fa-solid fa-clapperboard" aria-hidden="true"></i>
                                {{ number_format((int) $count, 0, ',', '.') }} seri
                            </small>
                        @endif
                    </span>
                </a>
            @endif
        @endforeach
    </section>

    @if (method_exists($people, 'links'))
        {{ $people->links() }}
    @endif
@endsection
