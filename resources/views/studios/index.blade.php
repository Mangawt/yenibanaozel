@extends('layouts.app')

@section('content')
    @php
        $total = method_exists($studios, 'total')
            ? $studios->total()
            : $studios->count();
    @endphp

    <section class="directory-hero nozu-directory-hero">
        <p class="eyebrow">Stüdyolar</p>
        <h1>Anime ve Manga Stüdyoları</h1>
        <p>Arşivdeki stüdyoları ve katkıda bulundukları serileri keşfet.</p>

        <strong class="directory-count">
            {{ number_format($total, 0, ',', '.') }} stüdyo
        </strong>
    </section>

    <section class="directory-grid nozu-directory-grid">
        @foreach ($studios as $studio)
            @php
                $name = is_array($studio)
                    ? ($studio['name'] ?? 'İsimsiz Stüdyo')
                    : ($studio->name ?? 'İsimsiz Stüdyo');

                $slug = is_array($studio)
                    ? ($studio['slug'] ?? null)
                    : ($studio->slug ?? null);

                $image = is_array($studio)
                    ? ($studio['image'] ?? $studio['logo'] ?? null)
                    : ($studio->image ?? $studio->logo ?? null);

                $count = is_array($studio)
                    ? ($studio['count'] ?? $studio['media_count'] ?? 0)
                    : ($studio->media_count ?? $studio->count ?? 0);
            @endphp

            @if ($slug)
                <a
                    class="directory-card nozu-directory-card"
                    href="{{ route('studios.show', ['slug' => $slug]) }}"
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

    @if (method_exists($studios, 'links'))
        {{ $studios->links() }}
    @endif
@endsection
