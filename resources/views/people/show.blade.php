@extends('layouts.app')

@php
    $voiceCount = $credits->where('kind', 'Seslendirme')->count();
    $staffCount = $credits->where('kind', '!=', 'Seslendirme')->count();
@endphp

@section('content')
    <section class="person-hero person-detail-hero">
        <div class="person-avatar hero-avatar">
            @if($person['image'])
                <img src="{{ $person['image'] }}" alt="{{ $person['name'] }}">
            @else
                <span>{{ mb_substr($person['name'], 0, 1) }}</span>
            @endif
        </div>
        <div>
            <p class="eyebrow">Sanatçı profili</p>
            <h1>{{ $person['name'] }}</h1>
            <p>{{ $credits->count() }} kayıtlı çalışma nozu.me arşivinde görünüyor.</p>
            <div class="person-stat-row">
                <span>{{ $voiceCount }} seslendirme</span>
                <span>{{ $staffCount }} ekip katkısı</span>
            </div>
        </div>
    </section>

    <section class="person-credit-layout">
        <aside class="person-mini-panel">
            <h2>Kısa Bilgi</h2>
            <p>Bu sayfa, kayıtlı anime ve manga içeriklerindeki karakter seslendirmeleri ve ekip katkılarından otomatik oluşturulur.</p>
            <div class="person-pill-grid">
                <span>Seslendirme {{ $voiceCount }}</span>
                <span>Ekip {{ $staffCount }}</span>
                <span>Toplam {{ $credits->count() }}</span>
            </div>
        </aside>

        <div>
            <x-section-title title="Çalışmaları" />
            <div class="credit-list enhanced">
                @foreach($credits as $credit)
                    <article class="credit-card enhanced">
                        <a class="credit-cover" href="{{ route('media.show', ['type' => $credit['media']->type, 'media' => $credit['media']]) }}">
                            @if($credit['media']->cover_image)
                                <img src="{{ $credit['media']->cover_image }}" alt="{{ $credit['media']->title }}">
                            @endif
                        </a>
                        <div>
                            <span>{{ $credit['kind'] }}</span>
                            <h2><a href="{{ route('media.show', ['type' => $credit['media']->type, 'media' => $credit['media']]) }}">{{ $credit['media']->title }}</a></h2>
                            <p>{{ $credit['role'] ?: 'Katkı' }}</p>
                            <small>{{ strtoupper($credit['media']->type) }} / {{ $credit['media']->format }} @if($credit['media']->average_score) / {{ $credit['media']->average_score }}% @endif</small>
                        </div>
                        @if($credit['image'])
                            <img class="credit-role-image" src="{{ $credit['image'] }}" alt="">
                        @endif
                    </article>
                @endforeach
            </div>
        </div>
    </section>
@endsection
