@extends('layouts.admin')

@section('title', ucfirst($type).' - Nozu CMS')

@section('content')
    <section class="admin-shell">
        @include('admin.partials.sidebar')

        <div class="admin-main">
            <section class="admin-hero">
                <div>
                    <p class="eyebrow">İçerik Yönetimi</p>
                    <h1>{{ $type === 'anime' ? 'Anime' : 'Manga' }}</h1>
                    <p>Kayıtları ara, filtrele, düzenle, public sayfayı önizle veya seçili içerikleri toplu sil.</p>
                </div>
                <span class="queue-live">{{ number_format($count, 0, ',', '.') }} kayıt</span>
            </section>

            <section class="panel">
                <h2>Filtreler</h2>
                <form class="filters" method="get" action="{{ $type === 'manga' ? route('admin.manga.index') : route('admin.anime.index') }}">
                    <input type="search" name="q" value="{{ request('q') }}" placeholder="Başlık ara">
                    <input name="status" value="{{ request('status') }}" placeholder="Durum">
                    <input name="format" value="{{ request('format') }}" placeholder="Format">
                    <input type="number" name="year" value="{{ request('year') }}" placeholder="Yıl">
                    <select name="sort">
                        <option value="newest" @selected(request('sort', 'newest') === 'newest')>En yeni</option>
                        <option value="oldest" @selected(request('sort') === 'oldest')>En eski</option>
                        <option value="title" @selected(request('sort') === 'title')>Başlık</option>
                        <option value="popularity" @selected(request('sort') === 'popularity')>Popülerlik</option>
                        <option value="score" @selected(request('sort') === 'score')>Puan</option>
                        <option value="updated" @selected(request('sort') === 'updated')>Güncellenme</option>
                    </select>
                    <button class="button primary">Filtrele</button>
                </form>
            </section>

            <section class="panel">
                <div class="section-head-row">
                    <h2>Kayıtlar</h2>
                    <span class="muted">{{ $items->count() }} kayıt listeleniyor</span>
                </div>

                @error('ids')
                    <p class="alert error">{{ $message }}</p>
                @enderror

                <form id="bulk-media-form" method="post" action="{{ route('admin.media.bulk-destroy') }}" onsubmit="return confirm('Seçilen içerikler kalıcı olarak silinsin mi?')">
                    @csrf
                    @method('DELETE')
                    <input type="hidden" name="type" value="{{ $type }}">

                    <div class="bulk-toolbar">
                        <label class="check">
                            <input type="checkbox" data-select-all-media>
                            Bu sayfadaki tümünü seç
                        </label>
                        <button class="button danger" disabled data-bulk-delete>
                            <i class="fa-solid fa-trash"></i> Seçilenleri sil
                        </button>
                    </div>

                    <div class="admin-table">
                        @forelse($items as $item)
                            <article class="media-admin-row selectable">
                                <label class="bulk-check" title="Seç">
                                    <input type="checkbox" name="ids[]" value="{{ $item->id }}" data-media-checkbox>
                                </label>
                                <a class="thumb" href="{{ route('media.show', ['type' => $item->type, 'media' => $item]) }}" target="_blank" rel="noopener">
                                    @if($item->cover_image)<img src="{{ $item->cover_image }}" alt="">@endif
                                </a>
                                <div class="media-admin-copy">
                                    <strong>{{ $item->title }}</strong>
                                    <span>{{ $item->format ?: '-' }} / {{ $item->status ?: '-' }} / {{ $item->start_year ?: '-' }}</span>
                                    <small>{{ collect($item->genres ?? [])->take(5)->implode(', ') }}</small>
                                </div>
                                <div class="row-metrics">
                                    <span>Puan <strong>{{ $item->average_score ?: '-' }}</strong></span>
                                    <span>Pop <strong>{{ $item->popularity ? number_format($item->popularity, 0, ',', '.') : '-' }}</strong></span>
                                </div>
                                <div class="row-actions">
                                    <a class="button" href="{{ route('media.show', ['type' => $item->type, 'media' => $item]) }}" target="_blank" rel="noopener">Önizle</a>
                                    <a class="button primary" href="{{ route('admin.media.edit', $item) }}">Düzenle</a>
                                </div>
                            </article>
                        @empty
                            <p class="muted">Kayıt bulunamadı.</p>
                        @endforelse
                    </div>
                </form>

                {{ $items->links() }}
            </section>
        </div>
    </section>

    <script>
        (() => {
            const selectAll = document.querySelector('[data-select-all-media]');
            const checks = [...document.querySelectorAll('[data-media-checkbox]')];
            const deleteButton = document.querySelector('[data-bulk-delete]');

            const sync = () => {
                const selected = checks.filter((check) => check.checked).length;
                if (deleteButton) {
                    deleteButton.disabled = selected === 0;
                    deleteButton.innerHTML = selected
                        ? `<i class="fa-solid fa-trash"></i> ${selected} kaydı sil`
                        : '<i class="fa-solid fa-trash"></i> Seçilenleri sil';
                }
                if (selectAll) {
                    selectAll.checked = checks.length > 0 && selected === checks.length;
                    selectAll.indeterminate = selected > 0 && selected < checks.length;
                }
            };

            selectAll?.addEventListener('change', () => {
                checks.forEach((check) => {
                    check.checked = selectAll.checked;
                });
                sync();
            });
            checks.forEach((check) => check.addEventListener('change', sync));
            sync();
        })();
    </script>
@endsection
