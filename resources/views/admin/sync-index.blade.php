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
                    <p>Scanner doğrudan media oluşturmaz; keşif yapar ve farkları Import Queue sistemine ekler.</p>
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
                    <select name="scan_scope"><option value="standard">Standart tarama</option><option value="full_catalog">Tüm katalog taraması</option></select>
                    <select name="mode"><option value="missing">Sadece eksikleri bul</option><option value="updates">Mevcutları güncelle</option><option value="full">Eksikleri ekle ve mevcutları güncelle</option></select>
                    <select name="sort"><option value="POPULARITY_DESC">Popülerlik</option><option value="TRENDING_DESC">Trend</option><option value="SCORE_DESC">Puan</option><option value="START_DATE_DESC">Başlama tarihi</option></select>
                    <input type="number" name="start_year" value="{{ now()->year }}" min="1900" max="2100" placeholder="Başlangıç yılı">
                    <input type="number" name="end_year" value="1900" min="1900" max="2100" placeholder="Bitiş yılı">
                    <input type="number" name="update_stale_after_days" value="7" min="0" max="365" placeholder="Son güncelleme yaşı">
                    <label class="check"><input type="checkbox" name="split_formats" value="1" checked> Formatlara böl</label>
                    <label class="check"><input type="checkbox" name="prioritize_active" value="1" checked> Güncel içerik önceliği</label>
                    <input type="number" name="page" value="1" min="1" placeholder="Başlangıç sayfası">
                    <input type="number" name="max_page" value="100" min="1" max="100" placeholder="Maks sayfa">
                    <input type="number" name="per_page" value="50" min="1" max="50" placeholder="Sayfa başı">
                    <input type="number" name="batch_size" value="1" min="1" max="5" placeholder="Chunk sayfa">
                    <input type="number" name="request_limit_per_minute" value="30" min="1" max="30" placeholder="Dakika isteği">
                    <input name="genre" placeholder="Tür">
                    <input type="number" name="year" min="1940" max="2100" placeholder="Standart yıl">
                    <select name="season"><option value="">Sezon</option><option value="WINTER">Kış</option><option value="SPRING">İlkbahar</option><option value="SUMMER">Yaz</option><option value="FALL">Sonbahar</option></select>
                    <input name="format" placeholder="Standart format">
                    <button class="button primary">Başlat</button>
                </form>
            </section>

            <section class="panel">
                <h2>Hızlı aksiyonlar</h2>
                <div class="test-grid">
                    @foreach([
                        ['label' => 'Anime eksiklerini tara', 'mode' => 'missing', 'scan_scope' => 'full_catalog', 'type' => 'anime'],
                        ['label' => 'Manga eksiklerini tara', 'mode' => 'missing', 'scan_scope' => 'full_catalog', 'type' => 'manga'],
                        ['label' => 'Anime mevcutları güncelle', 'mode' => 'updates', 'scan_scope' => 'full_catalog', 'type' => 'anime'],
                        ['label' => 'Manga mevcutları güncelle', 'mode' => 'updates', 'scan_scope' => 'full_catalog', 'type' => 'manga'],
                        ['label' => 'Anime tam katalog', 'mode' => 'full', 'scan_scope' => 'full_catalog', 'type' => 'anime'],
                        ['label' => 'Manga tam katalog', 'mode' => 'full', 'scan_scope' => 'full_catalog', 'type' => 'manga'],
                        ['label' => 'Aktif anime yayınları', 'mode' => 'updates', 'scan_scope' => 'standard', 'type' => 'anime', 'scheduled_run_type' => 'active'],
                        ['label' => 'Aktif manga yayınları', 'mode' => 'updates', 'scan_scope' => 'standard', 'type' => 'manga', 'scheduled_run_type' => 'active'],
                    ] as $action)
                        <form method="post" action="{{ route('admin.sync.start') }}">
                            @csrf
                            @foreach($action as $key => $value)
                                @if($key !== 'label')<input type="hidden" name="{{ $key }}" value="{{ $value }}">@endif
                            @endforeach
                            <input type="hidden" name="sort" value="POPULARITY_DESC">
                            <input type="hidden" name="per_page" value="50">
                            <input type="hidden" name="batch_size" value="1">
                            <input type="hidden" name="request_limit_per_minute" value="30">
                            <input type="hidden" name="max_page" value="100">
                            <input type="hidden" name="start_year" value="{{ now()->year }}">
                            <input type="hidden" name="end_year" value="1900">
                            <input type="hidden" name="update_stale_after_days" value="7">
                            <input type="hidden" name="split_formats" value="1">
                            <input type="hidden" name="prioritize_active" value="1">
                            <button class="button">{{ $action['label'] }}</button>
                        </form>
                    @endforeach
                </div>
            </section>

            <section class="panel">
                <h2>Tarama durumları</h2>
                <nav class="partition-filter-tabs">
                    @foreach([
                        'all' => 'Tümü',
                        'running' => 'Devam eden',
                        'completed' => 'Tamamlanan',
                        'failed' => 'Hatalı',
                        'pending' => 'Bekleyen',
                        'waiting_rate_limit' => 'Bekleme',
                    ] as $statusKey => $statusLabel)
                        <a class="{{ $partitionStatus === $statusKey ? 'active' : '' }}" href="{{ route('admin.sync.index', array_filter(['partition_status' => $statusKey === 'all' ? null : $statusKey])) }}">{{ $statusLabel }}</a>
                    @endforeach
                </nav>
                <div class="admin-table">
                    @foreach($states as $state)
                        @php
                            $filters = $state->filters ?? [];
                            $showPartitions = ($filters['scan_scope'] ?? 'standard') === 'full_catalog' && $state->partitions->isNotEmpty();
                        @endphp
                        <article class="sync-row">
                            <div>
                                <strong>#{{ $state->id }} {{ strtoupper($state->type) }} / {{ $state->mode }}</strong>
                                <span class="status {{ $state->status }}">{{ $state->status }}</span>
                                <small>Kapsam: {{ $filters['scan_scope'] ?? 'standard' }}</small>
                                <small>Yıl/Format/Sayfa: {{ $filters['current_year'] ?? '-' }} / {{ $filters['current_format'] ?? '-' }} / {{ $state->current_page }}</small>
                                <small>Son başarılı: {{ $filters['last_successful_year'] ?? '-' }} / {{ $filters['last_successful_format'] ?? '-' }} / {{ $state->last_successful_page }}</small>
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
                                @if(in_array($state->status, ['completed', 'failed', 'stopped', 'paused'], true))
                                    <form method="post" action="{{ route('admin.sync.destroy', $state) }}">@csrf @method('DELETE')<button>Sil</button></form>
                                @endif
                            </div>
                            @if($showPartitions)
                                @php
                                    $allPartitions = $state->partitions;
                                    $visiblePartitions = $partitionStatus === 'all'
                                        ? $allPartitions
                                        : $allPartitions->where('status', $partitionStatus);
                                    $formats = collect($filters['formats'] ?? [])->whenEmpty(fn ($items) => $allPartitions->pluck('format')->unique()->values());
                                    $years = $visiblePartitions->pluck('year')->unique()->values();
                                    if ($years->count() > 14) {
                                        $currentYear = (int) ($filters['current_year'] ?? $years->first());
                                        $years = $years->take(14);
                                        if (! $years->contains($currentYear)) {
                                            $years = $years->push($currentYear)->unique()->sortDesc()->values();
                                        }
                                    }
                                    $completedPartitions = $allPartitions->whereIn('status', ['completed', 'skipped'])->count();
                                    $runningPartitions = $allPartitions->where('status', 'running')->count();
                                    $waitingPartitions = $allPartitions->where('status', 'pending')->count();
                                    $failedPartitions = $allPartitions->where('status', 'failed')->count();
                                    $totalPartitions = max(1, $allPartitions->count());
                                    $progress = round(($completedPartitions / $totalPartitions) * 100, 2);
                                    $modeTitles = [
                                        'missing:anime' => 'Eksik Animeler',
                                        'updates:anime' => 'Güncellenen Animeler',
                                        'full:anime' => 'Anime Tam Katalog Durumu',
                                        'missing:manga' => 'Eksik Mangalar',
                                        'updates:manga' => 'Güncellenen Mangalar',
                                        'full:manga' => 'Manga Tam Katalog Durumu',
                                    ];
                                    $statusLabels = [
                                        'pending' => 'BEKLİYOR',
                                        'running' => 'DEVAM',
                                        'completed' => 'OK',
                                        'waiting_rate_limit' => 'BEKLEME',
                                        'paused' => 'DURAKLATILDI',
                                        'failed' => 'HATA',
                                        'skipped' => 'ATLANDI',
                                        'stopped' => 'DURDURULDU',
                                    ];
                                @endphp
                                <section class="partition-panel">
                                    <div class="partition-head">
                                        <div>
                                            <strong>{{ $modeTitles[$state->mode.':'.$state->type] ?? 'Katalog Durumu' }}</strong>
                                            <small>Tamamlanan partition: {{ $completedPartitions }} / {{ $allPartitions->count() }} · Devam eden: {{ $runningPartitions }} · Bekleyen: {{ $waitingPartitions }} · Hatalı: {{ $failedPartitions }} · İlerleme: %{{ number_format($progress, 2, ',', '.') }}</small>
                                        </div>
                                    </div>
                                    <div class="partition-table-wrap">
                                        <table class="partition-table">
                                            <thead>
                                                <tr>
                                                    <th>Yıl</th>
                                                    @foreach($formats as $format)
                                                        <th>{{ $format }}</th>
                                                    @endforeach
                                                </tr>
                                            </thead>
                                            <tbody>
                                                @forelse($years as $year)
                                                    <tr class="{{ (int)($filters['current_year'] ?? 0) === (int)$year ? 'active-year' : '' }}">
                                                        <th>{{ $year }}</th>
                                                        @foreach($formats as $format)
                                                            @php
                                                                $partition = $allPartitions->first(function ($item) use ($year, $format) {
                                                                    return (int) $item->year === (int) $year && $item->format === $format;
                                                                });
                                                                $hiddenByFilter = $partition && $partitionStatus !== 'all' && $partition->status !== $partitionStatus;
                                                            @endphp
                                                            <td>
                                                                @if($partition && ! $hiddenByFilter)
                                                                    <span class="partition-cell partition-{{ str_replace('_', '-', $partition->status) }}" title="İşlenen: {{ $partition->processed_count }} · Yeni: {{ $partition->imported_count }} · Güncellenen: {{ $partition->updated_count }} · Atlanan: {{ $partition->skipped_count }}">
                                                                        <b>{{ $statusLabels[$partition->status] ?? strtoupper($partition->status) }}</b>
                                                                        @if(in_array($partition->status, ['running', 'waiting_rate_limit'], true))
                                                                            <small>Sayfa {{ $partition->current_page }}@if($partition->last_page) / {{ $partition->last_page }}@endif</small>
                                                                        @elseif($partition->status === 'completed')
                                                                            <small>İşlenen: {{ $partition->processed_count }}</small>
                                                                            <small>Yeni: {{ $partition->imported_count }} · Güncellenen: {{ $partition->updated_count }} · Atlanan: {{ $partition->skipped_count }}</small>
                                                                        @elseif($partition->status === 'failed' && $partition->last_error)
                                                                            <small>{{ \Illuminate\Support\Str::limit($partition->last_error, 42) }}</small>
                                                                        @endif
                                                                    </span>
                                                                @else
                                                                    <span class="partition-cell partition-empty">-</span>
                                                                @endif
                                                            </td>
                                                        @endforeach
                                                    </tr>
                                                @empty
                                                    <tr><td colspan="{{ $formats->count() + 1 }}">Bu filtrede partition yok.</td></tr>
                                                @endforelse
                                            </tbody>
                                        </table>
                                    </div>
                                </section>
                            @endif
                        </article>
                    @endforeach
                </div>
                {{ $states->links() }}
            </section>
        </div>
    </section>
@endsection
