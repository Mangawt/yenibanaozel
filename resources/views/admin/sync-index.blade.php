@extends('layouts.admin')

@section('title', 'Smart Sync - Nozu CMS')

@section('content')
    <section class="admin-shell">
        @include('admin.partials.sidebar')

        <div class="admin-main">
            <section class="admin-hero">
                <div>
                    <p class="eyebrow">Smart Sync</p>
                    <h1>Nozu scanner</h1>
                    <p>Scanner doğrudan media oluşturmaz; farkları mevcut Import Queue sistemine ekler.</p>
                </div>
                <span class="queue-live">Queue: scanner</span>
            </section>

            <section class="metric-grid">
                <article><span>Running</span><strong>{{ $stats['running'] }}</strong></article>
                <article><span>Waiting</span><strong>{{ $stats['waiting'] }}</strong></article>
                <article><span>Completed</span><strong>{{ $stats['completed'] }}</strong></article>
                <article><span>Failed</span><strong>{{ $stats['failed'] }}</strong></article>
            </section>

            <section class="panel">
                <h2>Yeni tarama başlat</h2>
                <form class="filters queue-form" method="post" action="{{ route('admin.sync.start') }}">
                    @csrf
                    <select name="type"><option value="anime">Anime</option><option value="manga">Manga</option></select>
                    <select name="mode"><option value="missing">Eksikleri tara</option><option value="full">Tam senkronizasyon</option><option value="updates">Güncellemeleri tara</option></select>
                    <select name="sort"><option value="POPULARITY_DESC">Popülerlik</option><option value="TRENDING_DESC">Trend</option><option value="SCORE_DESC">Puan</option><option value="START_DATE_DESC">Başlama tarihi</option></select>
                    <input type="number" name="page" value="1" min="1" placeholder="Başlangıç sayfası">
                    <input type="number" name="max_page" value="100" min="1" max="5000" placeholder="Maks sayfa">
                    <input type="number" name="per_page" value="50" min="1" max="50" placeholder="Sayfa başı">
                    <input type="number" name="batch_size" value="1" min="1" max="5" placeholder="Chunk sayfa">
                    <input name="genre" placeholder="Tür">
                    <input type="number" name="year" min="1940" max="2100" placeholder="Yıl">
                    <select name="season"><option value="">Sezon</option><option value="WINTER">Kış</option><option value="SPRING">İlkbahar</option><option value="SUMMER">Yaz</option><option value="FALL">Sonbahar</option></select>
                    <input name="format" placeholder="Format">
                    <button class="button primary">Başlat</button>
                </form>
            </section>

            <section class="panel">
                <h2>Tarama durumları</h2>
                <div class="admin-table">
                    @foreach($states as $state)
                        <article class="sync-row">
                            <div>
                                <strong>#{{ $state->id }} {{ strtoupper($state->type) }} / {{ $state->mode }}</strong>
                                <span class="status {{ $state->status }}">{{ $state->status }}</span>
                                <small>Sayfa {{ $state->current_page }} / Son başarılı {{ $state->last_successful_page }}</small>
                            </div>
                            <div class="row-metrics">
                                <span>İşlenen <strong>{{ $state->processed_count }}</strong></span>
                                <span>Yeni <strong>{{ $state->imported_count }}</strong></span>
                                <span>Güncellenen <strong>{{ $state->updated_count }}</strong></span>
                                <span>Atlanan <strong>{{ $state->skipped_count }}</strong></span>
                                <span>Hata <strong>{{ $state->failed_count }}</strong></span>
                            </div>
                            <div>
                                <small>Sonraki: {{ $state->next_run_at?->format('d.m.Y H:i:s') ?: '-' }}</small>
                                @if($state->last_error)<small class="error-text">{{ $state->last_error }}</small>@endif
                            </div>
                            <div class="row-actions">
                                @if(in_array($state->status, ['running', 'waiting_rate_limit'], true))
                                    <form method="post" action="{{ route('admin.sync.pause', $state) }}">@csrf<button>Duraklat</button></form>
                                    <form method="post" action="{{ route('admin.sync.stop', $state) }}">@csrf<button>Durdur</button></form>
                                @elseif($state->status === 'paused')
                                    <form method="post" action="{{ route('admin.sync.resume', $state) }}">@csrf<button class="primary">Devam</button></form>
                                @endif
                            </div>
                        </article>
                    @endforeach
                </div>
                {{ $states->links() }}
            </section>
        </div>
    </section>
@endsection
