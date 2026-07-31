@extends('layouts.app')

@section('content')
    @php
        $total = method_exists($characters, 'total')
            ? $characters->total()
            : $characters->count();
    @endphp

    <section class="directory-hero nozu-directory-hero">
        <p class="eyebrow">Karakterler</p>
        <h1>Anime ve Manga Karakterleri</h1>
        <p>Arşivdeki karakterleri, görselleri ve hangi serilerde yer aldklarını keşfet.</p>

        <strong class="directory-count">
            {{ number_format($total, 0, ',', '.') }} karakter
        </strong>
    </section>

    <section class="directory-grid nozu-directory-grid">
        @foreach ($characters as $character)
            @php
                $name = is_array($character)
                    ? ($character['name'] ?? 'İsimsiz Karakter')
                    : ($character->name ?? 'İsimsiz Karakter');

                $slug = is_array($character)
                    ? ($character['slug'] ?? null)
                    : ($character->slug ?? null);

                $image = is_array($character)
                    ? ($character['image'] ?? null)
                    : ($character->image ?? null);

                $count = is_array($character)
                    ? ($character['count'] ?? 0)
                    : ($character->media_count ?? 0);
            @endphp

            @if ($slug)
                <a
                    class="directory-card nozu-directory-card"
                    href="{{ route('characters.show', ['slug' => $slug]) }}"
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

                        <small>
                            <i class="fa-solid fa-clapperboard" aria-hidden="true"></i>
                            {{ number_format((int) $count, 0, ',', '.') }} seri
                        </small>
                    </span>
                </a>
            @endif
        @endforeach
    </section>

    @if (method_exists($characters, 'links'))
        {{ $characters->links() }}
    @endif
@endsection
