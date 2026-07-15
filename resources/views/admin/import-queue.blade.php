@extends('layouts.admin')

@section('title', 'Import Queue - Nozu CMS')

@php
    $labels = [
        'total' => 'Toplam',
        'pending' => 'Pending',
        'running' => 'Running',
        'completed' => 'Completed',
        'failed' => 'Failed',
        'skipped' => 'Skipped',
        'processed' => 'Ä°ÅŸlenen',
        'remaining' => 'Kalan',
    ];
@endphp

@section('content')
    <section class="admin-shell">
        @include('admin.partials.sidebar')

        <div class="admin-main">
            <section class="admin-hero">
                <div>
                    <p class="eyebrow">Import Queue</p>
                    <h1>Production import sistemi</h1>
                    <p>Her enqueue iþlemi Laravel Bus batch oluÅŸturur; import arka planda tek worker ile gÃ¼venli ÅŸekilde yÃ¼rÃ¼r.</p>
                </div>
                <span class="queue-live">Queue worker</span>
            </section>

            <section class="metric-grid">
                @foreach($labels as $key => $label)
                    <article><span>{{ $label }}</span><strong data-stat="{{ $key }}">{{ number_format($stats[$key] ?? 0, 0, ',', '.') }}</strong></article>
                @endforeach
                <article><span>Yüzde</span><strong data-stat="percent">{{ number_format($stats['percent'] ?? 0, 2, ',', '.') }}%</strong></article>
                <article><span>Dakikadaki iþlem</span><strong data-stat="speed_per_minute">{{ number_format($stats['speed_per_minute'] ?? 0, 2, ',', '.') }}/dk</strong></article>
                <article><span>ETA</span><strong data-stat="eta_minutes">{{ $stats['eta_minutes'] ? $stats['eta_minutes'].' dk' : '-' }}</strong></article>
                <article><span>Þu an iþlenen</span><strong data-stat="current_series">{{ $stats['current_series'] ?? '-' }}</strong></article>
                <article><span>Son iþlenen</span><strong data-stat="last_series">{{ $stats['last_series'] ?? '-' }}</strong></article>
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
                <h2>Keþif oluÅŸtur</h2>
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
                        <option value="START_DATE_DESC">Baþlama tarihi</option>
                    </select>
                    <input name="genre" placeholder="Tür">
                    <input type="number" name="year" placeholder="Yýl" min="1940" max="2100">
                    <select name="season">
                        <option value="">Sezon</option>
                        <option value="WINTER">Kis</option>
                        <option value="SPRING">Ilkbahar</option>
                        <option value="SUMMER">Yaz</option>
                        <option value="FALL">Sonbahar</option>
                    </select>
                    <input name="format" placeholder="Biçim">
                    <input type="number" name="page" value="1" min="1" title="Baslangic sayfasi">
                    <input type="number" name="pages" value="10" min="1" max="100" title="Taranacak sayfa">
                    <input type="number" name="per_page" value="50" min="1" max="50" title="Sayfa baÅŸÄ± kayÄ±t">
                    <textarea name="links" rows="4" placeholder="Ýsteðe baðlý: Nozu kaynak linklerini veya ID'leri satýr satýr yapýþtýr"></textarea>
                    <button class="button primary">Önizle</button>
                </form>
            </section>

            @if($preview)
                <section class="panel preview-panel">
                    <h2>Kesif ozeti</h2>
                    <div class="metric-grid compact">
                        <article><span>Bulunan</span><strong>{{ $preview['found'] }}</strong></article>
                        <article><span>Anime</span><strong>{{ $preview['anime'] ?? 0 }}</strong></article>
                        <article><span>Manga</span><strong>{{ $preview['manga'] ?? 0 }}</strong></article>
                        <article><span>Veritabaninda var</span><strong>{{ $preview['existing_media'] }}</strong></article>
                        <article><span>Kuyrukta var</span><strong>{{ $preview['existing_queue'] }}</strong></article>
                        <article><span>Yeni eklenecek</span><strong>{{ $preview['new'] }}</strong></article>
                    </div>
                    <form method="post" action="{{ route('admin.import-queue.enqueue') }}">
                        @csrf
                        <button class="button primary">Kuyruga Ekle</button>
                    </form>
                </section>
            @endif

            <section class="panel">
                <h2>Queue filtreleri</h2>
                <form class="filters" method="get" action="{{ route('admin.import-queue') }}">
                    <select name="status">
                        <option value="">Tum durumlar</option>
                        @foreach(['pending', 'running', 'completed', 'failed', 'skipped'] as $status)
                            <option value="{{ $status }}" @selected(request('status') === $status)>{{ ucfirst($status) }}</option>
                        @endforeach
                    </select>
                    <select name="type">
                        <option value="">Tum turler</option>
                        <option value="anime" @selected(request('type') === 'anime')>Anime</option>
                        <option value="manga" @selected(request('type') === 'manga')>Manga</option>
                    </select>
                    <input name="source" value="{{ request('source') }}" placeholder="Kaynak">
                    <input type="number" name="external_id" value="{{ request('external_id') }}" placeholder="External ID">
                    <input name="batch_id" value="{{ request('batch_id') }}" placeholder="Batch ID">
                    <input name="error" value="{{ request('error') }}" placeholder="Hata ara">
                    <select name="sort">
                        <option value="newest" @selected(request('sort', 'newest') === 'newest')>En yeni</option>
                        <option value="oldest" @selected(request('sort') === 'oldest')>En eski</option>
                        <option value="status" @selected(request('sort') === 'status')>Durum</option>
                        <option value="attempts" @selected(request('sort') === 'attempts')>Deneme sayisi</option>
                        <option value="updated" @selected(request('sort') === 'updated')>Guncellenme</option>
                    </select>
                    <button class="button primary">Filtrele</button>
                </form>
            </section>

            <section class="panel">
                <h2>Toplu islemler</h2>
                <form class="filters" method="post" action="{{ route('admin.import-queue.action') }}">
                    @csrf
                    <select name="action" required>
                        <option value="">Islem sec</option>
                        <option value="retry_failed">Tüm failed kayýtlarý retry et</option>
                        <option value="clear_completed">Completed temizle</option>
                        <option value="clear_failed">Failed temizle</option>
                        <option value="clear_skipped">Skipped temizle</option>
                        <option value="clear_pending">Pending iptal et</option>
                    </select>
                    <button class="button">Uygula</button>
                </form>
            </section>

            <section class="panel">
                <h2>Kuyruk kayýtlarý</h2>
                <div class="queue-table">
                    @foreach($items as $item)
                        <article class="queue-row">
                            <div>
                                <strong>{{ strtoupper($item->type) }} #{{ $item->external_id }}</strong>
                                <p>{{ $item->status }} - Deneme: {{ $item->attempts }} @if($item->batch_id) - Batch: {{ \Illuminate\Support\Str::limit($item->batch_id, 8, '') }} @endif</p>
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
