@extends('layouts.admin')

@section('title', 'Şikayetler - Nozu CMS')

@section('content')
    <section class="admin-shell">
        @include('admin.partials.sidebar')

        <div class="admin-main">
            <section class="admin-hero compact-hero">
                <div>
                    <p class="eyebrow">Moderasyon</p>
                    <h1>Şikayetler</h1>
                </div>
            </section>

            <section class="panel">
                <div class="admin-table">
                    @forelse($reports as $report)
                        <article class="report-admin-row">
                            <div>
                                <strong>{{ ucfirst($report->reason) }} · {{ $report->status }}</strong>
                                <small>Bildiren: {{ $report->user?->username ? '@'.$report->user->username : 'Misafir' }}</small>
                                @if($report->details)<p>{{ $report->details }}</p>@endif
                                <small>{{ class_basename($report->reportable_type) }} #{{ $report->reportable_id }}</small>
                            </div>
                            <form class="inline-role-form" method="post" action="{{ route('admin.reports.update', $report) }}">
                                @csrf
                                @method('PUT')
                                <select name="status">
                                    <option value="open" @selected($report->status === 'open')>Açık</option>
                                    <option value="reviewed" @selected($report->status === 'reviewed')>İncelendi</option>
                                    <option value="closed" @selected($report->status === 'closed')>Kapandı</option>
                                </select>
                                <button class="button primary">Kaydet</button>
                            </form>
                            @if($report->reportable instanceof \App\Models\Comment)
                                <form method="post" action="{{ route('admin.comments.destroy', $report->reportable) }}">
                                    @csrf
                                    @method('DELETE')
                                    <button>Yorumu sil</button>
                                </form>
                            @endif
                        </article>
                    @empty
                        <p>Henüz şikayet yok.</p>
                    @endforelse
                </div>

                {{ $reports->links() }}
            </section>
        </div>
    </section>
@endsection
