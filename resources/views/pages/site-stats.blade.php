@extends('layouts.app')

@section('content')
    @php
        $accents = [
            ['#4F46E5', '#06B6D4'],
            ['#10B981', '#06B6D4'],
            ['#F59E0B', '#EF4444'],
            ['#8B5CF6', '#EC4899'],
        ];
    @endphp

    <main class="nozu-site-stats stats-board">
        <section class="site-stats-hero stats-board-hero">
            <span>nozu.me katalog durumu</span>
            <h1>Site İstatistikleri</h1>
            <p>Anime, manga, karakter ve ekip kataloglarının güncel hacmini ve kısa dönemli büyüme eğilimini sade bir panodan takip edebilirsin.</p>
        </section>

        <section class="stats-overview-grid">
            @foreach($stats as $stat)
                @php
                    $colors = $accents[$loop->index % count($accents)];
                @endphp
                <article class="stats-overview-card" style="--stat-a: {{ $colors[0] }}; --stat-b: {{ $colors[1] }};">
                    <i class="{{ $stat['icon'] }}"></i>
                    <span>{{ $stat['label'] }}</span>
                    <strong>{{ $stat['total_label'] }}</strong>
                    <small>Son gün +{{ number_format($stat['delta'], 0, ',', '.') }}</small>
                </article>
            @endforeach
        </section>

        <section class="stats-board-list">
            @foreach($stats as $stat)
                @php
                    $colors = $accents[$loop->index % count($accents)];
                    $values = collect($stat['values']);
                    $min = (int) $values->min();
                    $max = (int) $values->max();
                    $range = max(1, $max - $min);
                @endphp

                <article class="stats-board-card" style="--stat-a: {{ $colors[0] }}; --stat-b: {{ $colors[1] }};">
                    <div class="stats-board-card-main">
                        <div class="stats-board-icon">
                            <i class="{{ $stat['icon'] }}"></i>
                        </div>

                        <div class="stats-board-copy">
                            <span>{{ $stat['label'] }}</span>
                            <h2>{{ number_format($stat['total'], 0, ',', '.') }}</h2>
                            <p>Katalog kaydı</p>
                        </div>

                        <div class="stats-board-delta">
                            <strong>+{{ number_format($stat['delta'], 0, ',', '.') }}</strong>
                            <span>son gün</span>
                        </div>
                    </div>

                    <div class="stats-board-visual">
                        <svg viewBox="0 0 100 100" preserveAspectRatio="none" aria-hidden="true">
                            <polyline class="stats-board-area" points="0,100 {{ $stat['points'] }} 100,100" />
                            <polyline class="stats-board-line" points="{{ $stat['points'] }}" />
                        </svg>
                    </div>

                    <div class="stats-board-bars">
                        @foreach($stat['values'] as $valueIndex => $value)
                            @php
                                $height = 22 + (($value - $min) / $range * 78);
                            @endphp
                            <span
                                style="--bar-height: {{ round($height, 2) }}%;"
                                title="{{ $stat['dates'][$valueIndex] ?? '' }}: {{ number_format($value, 0, ',', '.') }}"
                            ></span>
                        @endforeach
                    </div>

                    <div class="stats-board-foot">
                        <span>{{ $stat['dates'][0] ?? '' }}</span>
                        <span>Son 15 gün</span>
                        <span>{{ $stat['dates'][count($stat['dates']) - 1] ?? '' }}</span>
                    </div>
                </article>
            @endforeach
        </section>
    </main>
@endsection
