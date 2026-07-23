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
                        @php
                            $reasonLabels = [
                                'wrong_info' => 'Yanlış anime bilgileri',
                                'wrong_images' => 'Yanlış Anime görselleri',
                                'wrong_summary' => 'Yanlış Anime özeti',
                                'translation_error' => 'Çeviri Hatası',
                                'translation_help' => 'Çeviri konusunda yardımcı olmak istiyor',
                                'comment' => 'Yorum şikayeti',
                                'profile' => 'Profil şikayeti',
                                'other' => 'Diğer',
                            ];
                        @endphp
                        <article class="report-admin-row">
                            <div>
                                <strong>{{ $reasonLabels[$report->reason] ?? ucfirst($report->reason) }} · {{ $report->status }}</strong>
                                <small>Bildiren: {{ $report->user?->username ? '@'.$report->user->username : 'Misafir' }}</small>
                                @if($report->details)<p>{{ $report->details }}</p>@endif
                                <small>
                                    {{ class_basename($report->reportable_type) }} #{{ $report->reportable_id }}
                                    @if($report->reportable instanceof \App\Models\Media)
                                        · <a href="{{ route('media.show', ['type' => $report->reportable->type, 'media' => $report->reportable]) }}" target="_blank" rel="noopener">Seriyi aç</a>
                                    @endif
                                </small>
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
                                <form method="post" action="{{ route('admin.comments.destroy', $report->reportable) }}" onsubmit="return confirm('Bu yorum silinsin mi?')">
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
