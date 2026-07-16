@extends('layouts.app')

@section('content')
    <article class="detail-hero" @if($media->banner_image) style="--hero:url('{{ $media->banner_image }}')" @endif>
        <div class="detail-shell">
            <div class="poster detail-poster">
                @if($media->cover_image)
                    <img src="{{ $media->cover_image }}" alt="{{ $media->title }}">
                @endif
            </div>
            <div class="detail-copy">
                <div class="badge-row">
                    <span>{{ $media->type === 'anime' ? 'Anime' : 'Manga' }}</span>
                    @if($media->format)<span>{{ $media->format }}</span>@endif
                    @if($media->status)<span>{{ $media->status }}</span>@endif
                </div>
                <h1>{{ $media->title }}</h1>
                @if($media->title_native)
                    <p class="muted">{{ $media->title_native }}</p>
                @endif
                @if($media->hashtag)
                    <p class="muted">{{ $media->hashtag }}</p>
                @endif
                <div class="hero-favorite-action">
                    @auth
                        <form method="post" action="{{ route('media.favorite', $media) }}">
                            @csrf
                            <button class="favorite-panel-button {{ $isFavorite ? 'active' : '' }}">
                                <i class="{{ $isFavorite ? 'fa-solid' : 'fa-regular' }} fa-heart"></i>
                                <span>{{ $isFavorite ? 'Favoride' : 'Favoriye ekle' }}</span>
                            </button>
                        </form>
                    @else
                        <a class="favorite-panel-button" href="{{ route('login') }}">
                            <i class="fa-regular fa-heart"></i>
                            <span>Favorilere ekle</span>
                        </a>
                    @endauth
                </div>
            </div>
        </div>
    </article>

    <nav class="detail-tabs pretty-tabs">
        <a href="#overview"><i class="fa-regular fa-file-lines"></i> Özet</a>
        <a href="#characters"><i class="fa-solid fa-users"></i> Karakterler</a>
        <a href="#staff"><i class="fa-solid fa-user-gear"></i> Ekip</a>
        <a href="#stats"><i class="fa-solid fa-chart-simple"></i> İstatistik</a>
        <a href="#links"><i class="fa-solid fa-link"></i> Bağlantılar</a>
        <a href="#comments"><i class="fa-regular fa-comments"></i> Yorumlar</a>
    </nav>

    <div class="content-layout nozu-detail">
        <aside class="side-panel">
            <div class="detail-action-card">
                @auth
                    <form class="media-list-form panel-list-form" method="post" action="{{ route('media.list', $media) }}">
                        @csrf
                        <label class="list-control-label"><i class="fa-solid fa-layer-group"></i> İzleme listesi</label>
                        <select name="status">
                            <option value="watching" @selected($listStatus === 'watching')>İzliyor</option>
                            <option value="reading" @selected($listStatus === 'reading')>Okuyor</option>
                            <option value="completed" @selected($listStatus === 'completed')>Tamamladı</option>
                            <option value="dropped" @selected($listStatus === 'dropped')>Bıraktı</option>
                            <option value="planned" @selected($listStatus === 'planned')>Planlıyor</option>
                        </select>
                        <button class="panel-save-button"><i class="fa-solid fa-check"></i> Kaydet</button>
                    </form>

                    @if($listStatus)
                        <form class="panel-remove-form" method="post" action="{{ route('media.list.remove', $media) }}">
                            @csrf
                            @method('DELETE')
                            <button class="panel-remove-button"><i class="fa-solid fa-xmark"></i> Listeden kaldır</button>
                        </form>
                    @endif
                @else
                    <a class="button primary" href="{{ route('login') }}"><i class="fa-solid fa-layer-group"></i> Listeye eklemek için giriş yap</a>
                @endauth
            </div>

            @if(count($media->rankings ?? []))
                <div class="ranking-box">
                    @foreach(array_slice($media->rankings, 0, 3) as $ranking)
                        <p><i class="fa-solid fa-star"></i> #{{ $ranking['rank'] }} {{ $ranking['context'] ?? 'Sıralama' }}</p>
                    @endforeach
                </div>
            @endif

            <div class="info-box info-cards">
                @if($media->next_airing_episode)<p><span>Yayın</span><strong>Bölüm {{ $media->next_airing_episode['episode'] ?? '-' }}</strong></p>@endif
                @if($media->format)<p><span>Biçim</span><strong>{{ $media->format }}</strong></p>@endif
                @if($media->episodes)<p><span>Bölüm</span><strong>{{ $media->episodes }}</strong></p>@endif
                @if($media->chapters)<p><span>Bölüm</span><strong>{{ $media->chapters }}</strong></p>@endif
                @if($media->volumes)<p><span>Cilt</span><strong>{{ $media->volumes }}</strong></p>@endif
                @if($media->duration)<p><span>Süre</span><strong>{{ $media->duration }} dk</strong></p>@endif
                @if($media->status)<p><span>Durum</span><strong>{{ $media->status }}</strong></p>@endif
                @if($media->title_native)<p><span>Japonca ad</span><strong>{{ $media->title_native }}</strong></p>@endif
                @if($media->title_english)<p><span>İngilizce ad</span><strong>{{ $media->title_english }}</strong></p>@endif
                @if($media->start_date)<p><span>Başlangıç</span><strong>{{ $media->start_date->format('d.m.Y') }}</strong></p>@endif
                @if($media->season || $media->season_year)<p><span>Sezon</span><strong>{{ trim(($media->season ?? '').' '.($media->season_year ?? '')) }}</strong></p>@endif
                @if($media->average_score)<p><span>Ortalama puan</span><strong>{{ $media->average_score }}%</strong></p>@endif
                @if($media->mean_score)<p><span>Genel puan</span><strong>{{ $media->mean_score }}%</strong></p>@endif
                @if($media->popularity)<p><span>Popülerlik</span><strong>{{ number_format($media->popularity, 0, ',', '.') }}</strong></p>@endif
                @if($media->favourites)<p><span>Favori</span><strong>{{ number_format($media->favourites, 0, ',', '.') }}</strong></p>@endif
                @if(count($media->studios ?? []))
                    <p><span>Stüdyo</span><strong>
                        @foreach($media->studios as $studio)
                            <a href="{{ route('studios.show', \Illuminate\Support\Str::slug($studio)) }}">{{ $studio }}</a>@if(! $loop->last), @endif
                        @endforeach
                    </strong></p>
                @endif
                @if(count($media->producers ?? []))
                    <p><span>Yapımcı</span><strong>
                        @foreach($media->producers as $producer)
                            <a href="{{ route('studios.show', \Illuminate\Support\Str::slug($producer)) }}">{{ $producer }}</a>@if(! $loop->last), @endif
                        @endforeach
                    </strong></p>
                @endif
                @if($media->source)<p><span>Kaynak türü</span><strong>{{ $media->source }}</strong></p>@endif
            </div>

            @if(count($media->genres ?? []))
                <div class="genre-box">
                    <h3>Türler</h3>
                    <div class="chips">
                        @foreach(($media->genres ?? []) as $genre)
                            <span>{{ $genre }}</span>
                        @endforeach
                    </div>
                </div>
            @endif
        </aside>

        <div class="main-column">
            <section class="content-section" id="overview">
                <h2>Özet</h2>
                <p class="summary">{{ $media->description ?: 'Bu içerik için henüz Türkçe özet eklenmedi.' }}</p>
            </section>

            @if(count($linkedRelations ?? []))
                <section class="content-section">
                    <h2>İlişkili Eserler</h2>
                    <div class="relation-grid">
                        @foreach(array_slice($linkedRelations, 0, 12) as $relation)
                            @php($linked = $relation['media'] ?? null)
                            <article class="relation-card {{ $linked ? 'is-linked' : '' }}">
                                @if(! empty($relation['cover_image']))
                                    <img src="{{ $relation['cover_image'] }}" alt="">
                                @endif
                                <div>
                                    <span>{{ $relation['relation_type'] ?? 'İlişkili' }}</span>
                                    <strong>
                                        @if($linked)
                                            <a href="{{ route('media.show', ['type' => $linked->type, 'media' => $linked]) }}">{{ $relation['title'] }}</a>
                                        @else
                                            {{ $relation['title'] }}
                                        @endif
                                    </strong>
                                    <small>{{ strtoupper($relation['type'] ?? '') }} &middot; {{ $relation['format'] ?? '' }} @if(! empty($relation['status'])) &middot; {{ $relation['status'] }} @endif</small>
                                </div>
                            </article>
                        @endforeach
                    </div>
                </section>
            @endif

            @if(count($media->characters ?? []))
                <section class="content-section" id="characters">
                    <h2>Karakterler</h2>
                    <div class="character-grid wide">
                        @foreach($media->characters as $character)
                            <article class="character-card split">
                                @if(! empty($character['image']))
                                    <img src="{{ $character['image'] }}" alt="{{ $character['name'] }}">
                                @endif
                                <div>
                                    <strong>{{ $character['name'] }}</strong>
                                    <span>{{ $character['role'] ?? 'Karakter' }}</span>
                                </div>
                                <div class="voice">
                                    @if(! empty($character['voice_actor']))
                                        <strong><a href="{{ route('people.show', ['slug' => \Illuminate\Support\Str::slug($character['voice_actor'])]) }}">{{ $character['voice_actor'] }}</a></strong>
                                        <span>{{ $character['language'] ?? 'Japonca' }}</span>
                                    @endif
                                </div>
                                @if(! empty($character['voice_actor_image']))
                                    <img src="{{ $character['voice_actor_image'] }}" alt="{{ $character['voice_actor'] }}">
                                @endif
                            </article>
                        @endforeach
                    </div>
                </section>
            @endif

            @if(count($media->staff ?? []))
                <section class="content-section" id="staff">
                    <h2>Ekip</h2>
                    <div class="staff-grid">
                        @foreach($media->staff as $person)
                            <article class="staff-card">
                                @if(! empty($person['image']))<img src="{{ $person['image'] }}" alt="{{ $person['name'] }}">@endif
                                <div>
                                    <strong><a href="{{ route('people.show', ['slug' => \Illuminate\Support\Str::slug($person['name'])]) }}">{{ $person['name'] }}</a></strong>
                                    <span>{{ $person['role'] }}</span>
                                </div>
                            </article>
                        @endforeach
                    </div>
                </section>
            @endif

            <section class="content-section" id="stats">
                <h2>İstatistik</h2>
                <div class="stat-grid inline">
                    @if($media->average_score)<div><span>Ortalama puan</span><strong>{{ $media->average_score }}%</strong></div>@endif
                    @if($media->mean_score)<div><span>Genel puan</span><strong>{{ $media->mean_score }}%</strong></div>@endif
                    @if($media->popularity)<div><span>Popülerlik</span><strong>{{ number_format($media->popularity, 0, ',', '.') }}</strong></div>@endif
                    @if($media->favourites)<div><span>Favori</span><strong>{{ number_format($media->favourites, 0, ',', '.') }}</strong></div>@endif
                    @if($media->start_year)<div><span>Yıl</span><strong>{{ $media->start_year }}</strong></div>@endif
                    @if($media->country_of_origin)<div><span>Ülke</span><strong>{{ $media->country_of_origin }}</strong></div>@endif
                </div>
            </section>

            @if(count($media->tags ?? []))
                <section class="content-section">
                    <h2>Etiketler</h2>
                    <div class="tag-cloud">
                        @foreach(array_slice($media->tags, 0, 18) as $tag)
                            <span>{{ $tag['name'] }} @if(! empty($tag['rank']))<small>{{ $tag['rank'] }}%</small>@endif</span>
                        @endforeach
                    </div>
                </section>
            @endif

            @if(count($media->external_links ?? []) || count($media->streaming_episodes ?? []))
                <section class="content-section" id="links">
                    <h2>Resmi ve İzleme Bağlantıları</h2>
                    <div class="external-grid">
                        @foreach($media->external_links ?? [] as $link)
                            <a href="{{ $link['url'] }}" target="_blank" rel="noopener">
                                @if(! empty($link['icon']))<img src="{{ $link['icon'] }}" alt="">@endif
                                <span>{{ $link['site'] }}</span>
                            </a>
                        @endforeach
                        @foreach($media->streaming_episodes ?? [] as $episode)
                            <a href="{{ $episode['url'] }}" target="_blank" rel="noopener">
                                @if(! empty($episode['thumbnail']))<img src="{{ $episode['thumbnail'] }}" alt="">@endif
                                <span>{{ $episode['site'] ?? $episode['title'] }}</span>
                            </a>
                        @endforeach
                    </div>
                </section>
            @endif

            <section class="content-section comments-section" id="comments">
                <h2>Yorumlar</h2>
                @auth
                    <form class="comment-form" method="post" action="{{ route('media.comment', $media) }}">
                        @csrf
                        <textarea name="body" rows="4" maxlength="2000" placeholder="Yorumunu yaz..." required></textarea>
                        <button class="button primary"><i class="fa-regular fa-paper-plane"></i> Yorum yap</button>
                    </form>
                @else
                    <p class="muted">Yorum yapmak için <a href="{{ route('login') }}">giriş yap</a>.</p>
                @endauth

                <div class="comment-list">
                    @forelse($comments as $comment)
                        @include('components.comment-card', ['comment' => $comment, 'media' => $media])
                    @empty
                        <p class="muted">Henüz yorum yok.</p>
                    @endforelse
                </div>

                {{ $comments->links() }}
            </section>
        </div>
    </div>

    @if($related->isNotEmpty())
        <x-section-title title="Bunları da beğenebilirsin" />
        <div class="poster-grid compact-posters related-grid">
            @foreach($related as $item)
                @include('components.media-card', ['item' => $item])
            @endforeach
        </div>
    @endif
@endsection
