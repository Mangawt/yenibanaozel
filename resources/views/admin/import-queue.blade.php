@extends('layouts.app')

@php
    $labels = [
        'waiting' => 'Bekliyor',
        'processing' => 'İşleniyor',
        'completed' => 'Tamamlandı',
        'skipped' => 'Atlandı',
        'failed' => 'Hatalı',
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
                    <h1>Güvenli toplu aktarım</h1>
                    <p>Önce sadece ID keşfi yapılır, ardından içerikler arka planda tek tek içe aktarılır.</p>
                </div>
                <form method="post" action="{{ route('admin.import-queue.process') }}">
                    @csrf
                    <button class="button primary">Sıradakini İşle</button>
                </form>
            </section>

            <section class="metric-grid">
                @foreach($labels as $key => $label)
                    <article><span>{{ $label }}</span><strong>{{ number_format($stats[$key] ?? 0, 0, ',', '.') }}</strong></article>
                @endforeach
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
                                <p>{{ $labels[$item->status] ?? $item->status }} · Deneme: {{ $item->attempts }}</p>
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
@endsection
