@extends('layouts.app')

@section('content')
    <section class="person-hero person-detail-hero">
        <div class="person-avatar hero-avatar">
            @if($character['image'])
                <x-responsive-image
                    :src="$character['image']"
                    :alt="$character['name']"
                    sizes="120px"
                    :widths="[160, 240]"
                    loading="eager"
                />
            @else
                <span>{{ mb_substr($character['name'], 0, 1) }}</span>
            @endif
        </div>
        <div>
            <p class="eyebrow">Karakter profili</p>
            <h1>{{ $character['name'] }}</h1>
            <p>{{ $credits->count() }} kayıtlı seri nozu.me arşivinde görünüyor.</p>
        </div>
    </section>

    <section class="person-credit-layout">
        <aside class="person-mini-panel">
            <h2>Kısa Bilgi</h2>
            <p>Bu sayfa, anime ve manga kayıtlarındaki karakter verilerinden otomatik oluşturulur.</p>
            <div class="person-pill-grid">
                <span>Seri {{ $credits->count() }}</span>
            </div>
        </aside>

        <div>
            <x-section-title title="Yer Aldığı Seriler" />
            <div class="credit-list enhanced">
                @foreach($credits as $credit)
                    <article class="credit-card enhanced">
                        <a class="credit-cover" href="{{ route('media.show', ['type' => $credit['media']->type, 'media' => $credit['media']]) }}">
                            @if($credit['media']->cover_image)
                                <x-responsive-image
                                    :src="$credit['media']->cover_image"
                                    :alt="$credit['media']->title"
                                    sizes="96px"
                                    :widths="[160, 240]"
                                />
                            @endif
                        </a>
                        <div>
                            <span>{{ $credit['role'] ?: 'Karakter' }}</span>
                            <h2><a href="{{ route('media.show', ['type' => $credit['media']->type, 'media' => $credit['media']]) }}">{{ $credit['media']->title }}</a></h2>
                            @if($credit['voice_actor'])
                                <p>Seslendiren: {{ $credit['voice_actor'] }}</p>
                            @endif
                            <small>{{ strtoupper($credit['media']->type) }} / {{ $credit['media']->format }} @if($credit['media']->average_score) / {{ $credit['media']->average_score }}% @endif</small>
                        </div>
                    </article>
                @endforeach
            </div>
        </div>
    </section>
@endsection
