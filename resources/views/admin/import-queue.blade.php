@extends('layouts.app')

@php
    $labels = [
        'total' => 'Toplam',
        'pending' => 'Pending',
        'running' => 'Running',
        'completed' => 'Completed',
        'failed' => 'Failed',
        'skipped' => 'Skipped',
        'processed' => 'İşlenen',
        'remaining' => 'Kalan',
    ];
@endphp

@section('content')
    <section class="admin-shell">
        <aside class="admin-sidebar">
            <strong>nozu.me CMS</strong>
            <a href="{{ route('admin.dashboard') }}">Genel Bakış</a>
            <a class="active" href="{{ route('admin.import-queue') }}">Import Queue</a>
            <a href="{{ route('admin.settings') }}">Ayarlar</a>
            <form method="post" action="{{ route('admin.logout') }}">@csrf<button>Çıkış</button></form>
        </aside>

        <div class="admin-main">
            <section class="admin-hero">
                <div>
                    <p class="eyebrow">Import Queue</p>
                    <h1>Production import sistemi</h1>
                    <p>Her enqueue işlemi Laravel Bus batch oluşturur; import arka planda tek worker ile güvenli şekilde yürür.</p>
                </div>
                <span class="queue-live">Queue worker</span>
            </section>

            <section class="metric-grid">
                @foreach($labels as $key => $label)
                    <article><span>{{ $label }}</span><strong data-stat="{{ $key }}">{{ number_format($stats[$key] ?? 0, 0, ',', '.') }}</strong></article>
                @endforeach
                <article><span>Yüzde</span><strong data-stat="percent">{{ number_format($stats['percent'] ?? 0, 2, ',', '.') }}%</strong></article>
                <article><span>Dakikadaki işlem</span><strong data-stat="speed_per_minute">{{ number_format($stats['speed_per_minute'] ?? 0, 2, ',', '.') }}/dk</strong></article>
                <article><span>ETA</span><strong data-stat="eta_minutes">{{ $stats['eta_minutes'] ? $stats['eta_minutes'].' dk' : '-' }}</strong></article>
                <article><span>Şu an işlenen</span><strong data-stat="current_series">{{ $stats['current_series'] ?? '-' }}</strong></article>
                <article><span>Son işlenen</span><strong data-stat="last_series">{{ $stats['last_series'] ?? '-' }}</strong></article>
            </section>

            <section class="panel batch-panel">
                <h2>Son Batch</h2>
                <div class="metric-grid compact">
                    <article><span>Batch</span><strong data-batch="name">{{ $stats['batch']['name'] ?? '-' }}</strong></article>
                    <article><span>Total Jobs</span><strong data-batch="total_jobs">{{ $stats['batch']['total_jobs'] ?? 0 }}</strong></article>
                    <article><span>Pending</span><strong data-batch="pending_jobs">{{ $stats['batch']['pending_jobs'] ?? 0 }}</strong></article>
                    <article><span>Processed</span><strong data-batch="processed_jobs">{{ $stats['batch']['processed_jobs'] ?? 0 }}</strong></article>
                    <article><span>Failed</span><strong data-batch="failed_jobs">{{ $stats['batch']['failed_jobs'] ?? 0 }}</strong></article>
                    <article><span>Progress</span><strong data-batch="progress">{{ $stats['batch']['progress'] ?? 0 }}%</strong></article>
                </div>
            </section>

            <section class="panel">
                <h2>Keşif oluştur</h2>
                <form class="filters queue-form" method="post" action="{{ route('admin.import-queue.preview') }}">
                    @csrf
                    <select name="type">
                        <option value="anime">Anime</option>
                        <option value="manga">Manga</option>
                    </select>
                    <select name="sort">
                        <option value="POPULARITY_DESC">Popülerlik</option>
                        <option value="TRENDING_DESC">Trend</option>
                        <option value="SCORE_DESC">Puan</option>
                        <option value="START_DATE_DESC">Başlama tarihi</option>
                    </select>
                    <input name="genre" placeholder="Tür">
                    <input type="number" name="year" placeholder="Yıl" min="1940" max="2100">
                    <select name="season">
                        <option value="">Sezon</option>
                        <option value="WINTER">Kış</option>
                        <option value="SPRING">İlkbahar</option>
                        <option value="SUMMER">Yaz</option>
                        <option value="FALL">Sonbahar</option>
                    </select>
                    <input name="format" placeholder="Biçim">
                    <input type="number" name="page" value="1" min="1" title="Başlangıç sayfası">
                    <input type="number" name="pages" value="10" min="1" max="100" title="Taranacak sayfa">
                    <input type="number" name="per_page" value="50" min="1" max="50" title="Sayfa başı kayıt">
                    <textarea name="links" rows="4" placeholder="İsteğe bağlı: AniList linklerini veya ID'leri satır satır yapıştır"></textarea>
                    <button class="button primary">Önizle</button>
                </form>
            </section>

            @if($preview)
                <section class="panel preview-panel">
                    <h2>Keşif özeti</h2>
                    <div class="metric-grid compact">
                        <article><span>Bulunan</span><strong>{{ $preview['found'] }}</strong></article>
                        <article><span>Veritabanında var</span><strong>{{ $preview['existing_media'] }}</strong></article>
                        <article><span>Kuyrukta var</span><strong>{{ $preview['existing_queue'] }}</strong></article>
                        <article><span>Yeni eklenecek</span><strong>{{ $preview['new'] }}</strong></article>
                    </div>
                    <form method="post" action="{{ route('admin.import-queue.enqueue') }}">
                        @csrf
                        <button class="button primary">Kuyruğa Ekle</button>
                    </form>
                </section>
            @endif

            <section class="panel">
                <h2>Kuyruk kayıtları</h2>
                <div class="queue-table">
                    @foreach($items as $item)
                        <article class="queue-row">
                            <div>
                                <strong>{{ strtoupper($item->type) }} #{{ $item->external_id }}</strong>
                                <p>{{ $item->status }} · Deneme: {{ $item->attempts }} @if($item->batch_id) · Batch: {{ \Illuminate\Support\Str::limit($item->batch_id, 8, '') }} @endif</p>
                                @if($item->error_message)
                                    <small>{{ $item->error_message }}</small>
                                @endif
                            </div>
                            @if($item->status === 'failed')
                                <form method="post" action="{{ route('admin.import-queue.retry', $item) }}">
                                    @csrf
                                    <button class="button">Tekrar Dene</button>
                                </form>
                            @endif
                        </article>
                    @endforeach
                </div>
                {{ $items->links() }}
            </section>
        </div>
    </section>
    <script>
        const formatNumber = (value, digits = 0) => Number(value || 0).toLocaleString('tr-TR', {
            minimumFractionDigits: digits,
            maximumFractionDigits: digits
        });

        setInterval(async () => {
            const response = await fetch('{{ route('admin.import-queue.stats') }}', {headers: {'Accept': 'application/json'}});
            const stats = await response.json();
            document.querySelectorAll('[data-stat]').forEach((node) => {
                const key = node.dataset.stat;
                const value = stats[key] ?? 0;
                node.textContent = key === 'speed_per_minute'
                    ? formatNumber(value, 2) + '/dk'
                    : key === 'percent'
                        ? formatNumber(value, 2) + '%'
                        : key === 'eta_minutes'
                            ? (value ? value + ' dk' : '-')
                            : ['current_series', 'last_series'].includes(key)
                                ? (value || '-')
                                : formatNumber(value);
            });

            const batch = stats.batch || {};
            document.querySelectorAll('[data-batch]').forEach((node) => {
                const key = node.dataset.batch;
                const value = batch[key] ?? (key === 'name' ? '-' : 0);
                node.textContent = key === 'progress' ? value + '%' : value;
            });
        }, 5000);
    </script>
@endsection
