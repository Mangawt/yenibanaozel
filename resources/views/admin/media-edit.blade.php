@extends('layouts.admin')

@section('title', 'İçerik Düzenle - Nozu CMS')

@section('content')
    <section class="admin-shell">
        @include('admin.partials.sidebar')

        <form class="admin-main settings-form" method="post" action="{{ route('admin.media.update', $media) }}">
            @csrf
            @method('PUT')

            <section class="admin-hero">
                <div>
                    <p class="eyebrow">{{ strtoupper($media->type) }} #{{ $media->id }}</p>
                    <h1>{{ $media->title }}</h1>
                    <p>Temel bilgileri duzenle. Karakter, staff ve iliskiler import verisinden korunur.</p>
                </div>
                <div class="hero-actions">
                    <a class="button" href="{{ route('media.show', ['type' => $media->type, 'media' => $media]) }}" target="_blank" rel="noopener">Public Önizle</a>
                    <button class="button primary">Kaydet</button>
                </div>
            </section>

            <section class="edit-grid">
                <div class="panel">
                    <h2>Başlık ve özet</h2>
                    <label>Başlık</label>
                    <input name="title" value="{{ old('title', $media->title) }}" required>
                    <label>Ingilizce baslik</label>
                    <input name="title_english" value="{{ old('title_english', $media->title_english) }}">
                    <label>Orijinal baslik</label>
                    <input name="title_native" value="{{ old('title_native', $media->title_native) }}">
                    <label>Turkce ozet</label>
                    <textarea name="description" rows="12">{{ old('description', $media->description) }}</textarea>
                </div>

                <div class="panel">
                    <h2>Meta</h2>
                    <label>Format</label>
                    <input name="format" value="{{ old('format', $media->format) }}">
                    <label>Durum</label>
                    <input name="status" value="{{ old('status', $media->status) }}">
                    <label>Sezon</label>
                    <input name="season" value="{{ old('season', $media->season) }}">
                    <label>Sezon yili</label>
                    <input type="number" name="season_year" value="{{ old('season_year', $media->season_year) }}">
                    <label>Baslangic yili</label>
                    <input type="number" name="start_year" value="{{ old('start_year', $media->start_year) }}">
                    <label class="check"><input type="checkbox" name="is_adult" value="1" @checked(old('is_adult', $media->is_adult))> Yetişkin içerik</label>
                </div>
            </section>

            <section class="edit-grid">
                <div class="panel">
                    <h2>Sayilar</h2>
                    <label>Ortalama puan</label>
                    <input type="number" name="average_score" value="{{ old('average_score', $media->average_score) }}" min="0" max="100">
                    <label>Genel puan</label>
                    <input type="number" name="mean_score" value="{{ old('mean_score', $media->mean_score) }}" min="0" max="100">
                    <label>Populerlik</label>
                    <input type="number" name="popularity" value="{{ old('popularity', $media->popularity) }}" min="0">
                    <label>Favori</label>
                    <input type="number" name="favourites" value="{{ old('favourites', $media->favourites) }}" min="0">
                    <label>Bolum</label>
                    <input type="number" name="episodes" value="{{ old('episodes', $media->episodes) }}" min="0">
                    <label>Chapter</label>
                    <input type="number" name="chapters" value="{{ old('chapters', $media->chapters) }}" min="0">
                    <label>Cilt</label>
                    <input type="number" name="volumes" value="{{ old('volumes', $media->volumes) }}" min="0">
                    <label>Sure</label>
                    <input type="number" name="duration" value="{{ old('duration', $media->duration) }}" min="0">
                </div>

                <div class="panel">
                    <h2>Listeler</h2>
                    <label>Turler</label>
                    <textarea name="genres_text" rows="3">{{ old('genres_text', collect($media->genres ?? [])->implode(', ')) }}</textarea>
                    <label>Stüdyolar</label>
                    <textarea name="studios_text" rows="3">{{ old('studios_text', collect($media->studios ?? [])->implode(', ')) }}</textarea>
                    <label>Yapimcilar</label>
                    <textarea name="producers_text" rows="3">{{ old('producers_text', collect($media->producers ?? [])->implode(', ')) }}</textarea>
                    <label>Alternatif adlar</label>
                    <textarea name="synonyms_text" rows="3">{{ old('synonyms_text', collect($media->synonyms ?? [])->implode(', ')) }}</textarea>
                </div>
            </section>
        </form>

        <form class="danger-zone" method="post" action="{{ route('admin.media.destroy', $media) }}" onsubmit="return confirm('Bu içerik silinsin mi?')">
            @csrf
            @method('DELETE')
            <button>Bu icerigi sil</button>
        </form>
    </section>
@endsection
