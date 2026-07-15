@extends('layouts.admin')

@section('title', 'Sistem Durumu - Nozu CMS')

@section('content')
    <section class="admin-shell">
        @include('admin.partials.sidebar')

        <div class="admin-main">
            <section class="admin-hero">
                <div>
                    <p class="eyebrow">Operasyon</p>
                    <h1>Sistem Durumu</h1>
                    <p>Import queue, failed jobs ve son log kayıtlarını tek ekrandan izle.</p>
                </div>
                <span class="queue-live">Canlı kontrol</span>
            </section>

            <section class="metric-grid">
                <article><span>Toplam queue</span><strong>{{ $queueStats['total'] }}</strong></article>
                <article><span>Bekleyen job</span><strong>{{ $jobs['waiting'] }}</strong></article>
                <article><span>Failed jobs</span><strong>{{ $jobs['failed'] }}</strong></article>
                <article><span>Batch</span><strong>{{ $jobs['batches'] }}</strong></article>
            </section>

            <section class="metric-grid compact">
                <article><span>Pending</span><strong>{{ $queueStats['pending'] }}</strong></article>
                <article><span>Running</span><strong>{{ $queueStats['running'] }}</strong></article>
                <article><span>Completed</span><strong>{{ $queueStats['completed'] }}</strong></article>
                <article><span>Failed</span><strong>{{ $queueStats['failed'] }}</strong></article>
                <article><span>Hız / dk</span><strong>{{ $queueStats['speed_per_minute'] }}</strong></article>
                <article><span>ETA</span><strong>{{ $queueStats['eta_minutes'] ? $queueStats['eta_minutes'].' dk' : '-' }}</strong></article>
            </section>

            <section class="panel">
                <h2>Son failed jobs</h2>
                <div class="admin-table">
                    @forelse($latestFailedJobs as $job)
                        <article class="queue-row">
                            <div>
                                <strong>{{ $job->queue }}</strong>
                                <small>{{ $job->failed_at }}</small>
                                <small>{{ mb_substr($job->exception, 0, 240) }}</small>
                            </div>
                        </article>
                    @empty
                        <p class="muted">Failed job yok.</p>
                    @endforelse
                </div>
            </section>

            <section class="panel">
                <h2>Son loglar</h2>
                <div class="log-grid">
                    @foreach($logs as $name => $lines)
                        <article class="log-panel">
                            <h3>{{ $name }}</h3>
                            @forelse($lines as $line)
                                <code>{{ $line }}</code>
                            @empty
                                <p class="muted">Log kaydı yok.</p>
                            @endforelse
                        </article>
                    @endforeach
                </div>
            </section>
        </div>
    </section>
@endsection
