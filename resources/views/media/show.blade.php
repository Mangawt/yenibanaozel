@extends('layouts.app')

@section('content')
    <div class="nozu-detail-page">
        <section class="nozu-media-banner" @if($media->banner_image) style="--hero:url('{{ $media->banner_image }}')" @endif>
            <div class="nozu-banner-inner"></div>
        </section>

        <div class="nozu-detail-grid">
            <aside class="nozu-left-rail">
                <div class="nozu-cover-card">
                    @if($media->cover_image)
                        <x-responsive-image
                            :src="$media->cover_image"
                            :alt="$media->title"
                            sizes="(max-width: 760px) 120px, 220px"
                            loading="eager"
                            fetchpriority="high"
                        />
                    @endif
                </div>

                <div class="nozu-library-actions">
                    @auth
                        <span>Kitaplığına ekle</span>
                        <form method="post" action="{{ route('media.favorite', $media) }}">
                            @csrf
                            <button class="nozu-library-button favorite {{ $isFavorite ? 'active' : '' }}">
                                {{ $isFavorite ? 'Favoride' : 'Favoriye ekle' }}
                            </button>
                        </form>

                        <form class="nozu-list-form" method="post" action="{{ route('media.list', $media) }}">
                            @csrf
                            <select name="status" aria-label="Liste durumu">
                                <option value="watching" @selected($listStatus === 'watching')>İzliyor</option>
                                <option value="reading" @selected($listStatus === 'reading')>Okuyor</option>
                                <option value="completed" @selected($listStatus === 'completed')>Tamamladı</option>
                                <option value="paused" @selected($listStatus === 'paused')>Duraklatıldı</option>
                                <option value="dropped" @selected($listStatus === 'dropped')>Bıraktı</option>
                                <option value="planned" @selected($listStatus === 'planned')>Planlıyor</option>
                            </select>
                            <button>Kaydet</button>
                        </form>

                        @if($listStatus)
                            <form method="post" action="{{ route('media.list.remove', $media) }}">
                                @csrf
                                @method('DELETE')
                                <button class="nozu-library-button remove">Listeden kaldır</button>
                            </form>
                        @endif
                    @else
                        <a class="nozu-library-button favorite login-required-button" href="{{ route('login') }}">
                            <i class="fa-solid fa-right-to-bracket"></i>
                            <span>Listeye eklemek için giriş yap</span>
                        </a>
                    @endauth
                </div>

                @if($media->turkish_purchase_url || count($media->external_links ?? []))
                    <div class="nozu-watch-links">
                        <span>İnternetten izle / satın al</span>
                        <div class="nozu-watch-link-grid">
                            @if($media->turkish_purchase_url)
                                <a class="nozu-watch-link purchase-link" href="{{ $media->turkish_purchase_url }}" target="_blank" rel="noopener" title="Türkçe satın al">
                                    <i class="fa-solid fa-cart-shopping"></i>
                                </a>
                            @endif
                            @foreach(array_slice($media->external_links ?? [], 0, 4) as $link)
                                @php
                                    $rawIcon = collect(data_get($media->raw_payload, 'externalLinks', []))
                                        ->firstWhere('url', $link['url'] ?? null)['icon'] ?? null;
                                    $linkIcon = $link['icon'] ?? $rawIcon;
                                    $siteName = strtolower((string) ($link['site'] ?? ''));
                                    $iconClass = 'fa-solid fa-arrow-up-right-from-square';
                                    if (str_contains($siteName, 'youtube')) {
                                        $iconClass = 'fa-brands fa-youtube';
                                    } elseif (str_contains($siteName, 'twitter') || $siteName === 'x') {
                                        $iconClass = 'fa-brands fa-x-twitter';
                                    } elseif (str_contains($siteName, 'crunchyroll')) {
                                        $iconClass = 'fa-solid fa-play';
                                    } elseif (str_contains($siteName, 'netflix')) {
                                        $iconClass = 'fa-solid fa-n';
                                    } elseif (str_contains($siteName, 'official')) {
                                        $iconClass = 'fa-solid fa-globe';
                                    }
                                @endphp
                                <a class="nozu-watch-link {{ ! empty($linkIcon) ? 'has-logo' : '' }}" href="{{ $link['url'] }}" target="_blank" rel="noopener" title="{{ $link['site'] }}">
                                    @if(! empty($linkIcon))
                                        <img src="{{ $linkIcon }}" alt="{{ $link['site'] ?? '' }}">
                                    @else
                                        <i class="{{ $iconClass }}"></i>
                                    @endif
                                </a>
                            @endforeach
                        </div>
                    </div>
                @endif

                @if(count($media->rankings ?? []))
                    <div class="nozu-rank-lines">
                        @foreach(array_slice($media->rankings, 0, 2) as $ranking)
                            @php
                                $context = (string) ($ranking['context'] ?? '');
                                $contextLower = mb_strtolower($context, 'UTF-8');
                                $rankIcon = 'fa-solid fa-trophy';
                                if (str_contains($contextLower, 'popüler')) {
                                    $rankIcon = 'fa-solid fa-fire';
                                } elseif (str_contains($contextLower, 'puan')) {
                                    $rankIcon = 'fa-solid fa-star';
                                }
                            @endphp
                            <p class="rank-pill">
                                <span class="rank-icon"><i class="{{ $rankIcon }}"></i></span>
                                <span class="rank-copy">
                                    <strong>#{{ $ranking['rank'] }}</strong>
                                    <small>{{ $context ?: 'Sıralama' }}</small>
                                </span>
                            </p>
                        @endforeach
                    </div>
                @endif
            </aside>

            <main class="nozu-center-column">
                <nav class="nozu-tabs nozu-media-tabs" data-media-tabs>
                    <a class="is-active" href="#overview" data-tab-link="overview"><i class="fa-regular fa-file-lines"></i><span>Özet</span></a>
                    <a href="#stats" data-tab-link="stats"><i class="fa-solid fa-chart-simple"></i><span>İstatistikler</span></a>
                    @if(count($media->characters ?? []) || count($media->staff ?? []))<a href="#characters" data-tab-link="characters"><i class="fa-solid fa-users-gear"></i><span>Karakterler ve Ekip</span></a>@endif
                    <a href="#comments" data-tab-link="comments"><i class="fa-regular fa-comments"></i><span>Yorumlar</span></a>
                    @if(count($linkedRelations ?? []))<a href="#links" data-tab-link="links"><i class="fa-solid fa-link"></i><span>İlişkili Seriler</span></a>@endif
                </nav>

                <section class="nozu-title-section nozu-tab-panel is-active" id="overview" data-tab-panel="overview">
                    <h1>{{ $media->title }}</h1>
                    @if($media->average_score)
                        <strong class="nozu-score">Topluluk tarafından %{{ $media->average_score }} oranında beğenildi</strong>
                    @endif
                    <div class="nozu-summary">
                        @if(filled($media->description))
                            {!! $media->description !!}
                        @else
                            Bu içerik için henüz Türkçe özet eklenmedi.
                        @endif
                    </div>
                    <a class="nozu-read-more" href="#comments" data-tab-link="comments">Yorumlara geç</a>

                    @if(count($media->genres ?? []))
                        <div class="nozu-tags">
                            @foreach(array_slice($media->genres, 0, 10) as $genre)
                                <a href="{{ route('search', ['genre' => $genre]) }}">{{ $genre }}</a>
                            @endforeach
                        </div>
                    @endif
                </section>

                @if(count($linkedRelations ?? []))
                    <section class="nozu-content-block nozu-tab-panel" id="links" data-tab-panel="links">
                        <h2>İlişkili Seriler</h2>
                        <div class="nozu-relation-list">
                            @foreach(array_slice($linkedRelations, 0, 8) as $relation)
                                @php
                                    $linked = $relation['media'] ?? null;
                                @endphp
                                <article>
                                    @php
                                        $relationCover = $linked?->cover_image ?? ($relation['cover_image'] ?? null);
                                    @endphp
                                    @if(! empty($relationCover))
                                        <x-responsive-image
                                            :src="$relationCover"
                                            :alt="$linked?->title ?? ($relation['title'] ?? '')"
                                            sizes="72px"
                                            :widths="[96, 160]"
                                        />
                                    @else
                                        <span class="nozu-image-fallback relation-fallback">{{ mb_substr($linked?->title ?? ($relation['title'] ?? 'R'), 0, 1) }}</span>
                                    @endif
                                    <div>
                                        <span>{{ $relation['relation_type'] ?? 'İlişkili' }}</span>
                                        <strong>
                                            @if($linked)
                                                <a href="{{ route('media.show', ['type' => $linked->type, 'media' => $linked]) }}">{{ $linked->title }}</a>
                                            @else
                                                {{ $relation['title'] }}
                                            @endif
                                        </strong>
                                        <small>{{ strtoupper($relation['type'] ?? '') }} &middot; {{ $relation['format'] ?? '' }}</small>
                                    </div>
                                </article>
                            @endforeach
                        </div>
                    </section>
                @endif

                @if(count($media->characters ?? []) || count($media->staff ?? []))
                    <section class="nozu-content-block nozu-tab-panel" id="characters" data-tab-panel="characters">
                        @if(count($media->characters ?? []))
                            <div class="nozu-combined-heading">
                                <h2>Karakterler</h2>
                            </div>
                            <div class="nozu-character-strip">
                                @foreach(array_slice($media->characters, 0, 8) as $character)
                                    <article>
                                        @if(! empty($character['image']))
                                            <x-responsive-image
                                                :src="$character['image']"
                                                :alt="$character['name']"
                                                sizes="64px"
                                                :widths="[96, 160]"
                                            />
                                        @else
                                            <span class="nozu-image-fallback">{{ mb_substr($character['name'] ?? 'K', 0, 1) }}</span>
                                        @endif
                                        <div>
                                            <strong>{{ $character['name'] }}</strong>
                                            <span>{{ $character['role'] ?? 'Karakter' }}</span>
                                        </div>
                                    </article>
                                @endforeach
                            </div>
                        @endif

                        @if(count($media->staff ?? []))
                            <div class="nozu-combined-heading">
                                <h2>Ekip</h2>
                            </div>
                            <div class="nozu-staff-list">
                                @foreach(array_slice($media->staff, 0, 12) as $person)
                                    <article>
                                        @if(! empty($person['image']))
                                            <x-responsive-image
                                                :src="$person['image']"
                                                :alt="$person['name']"
                                                sizes="64px"
                                                :widths="[96, 160]"
                                            />
                                        @else
                                            <span class="nozu-image-fallback">{{ mb_substr($person['name'] ?? 'E', 0, 1) }}</span>
                                        @endif
                                        <div>
                                            <strong><a href="{{ route('people.show', ['slug' => \Illuminate\Support\Str::slug($person['name'])]) }}">{{ $person['name'] }}</a></strong>
                                            <span>{{ $person['role'] }}</span>
                                        </div>
                                    </article>
                                @endforeach
                            </div>
                        @endif
                    </section>
                @endif

                <section class="nozu-content-block nozu-tab-panel" id="stats" data-tab-panel="stats">
                    <h2>İstatistikler</h2>
                    @php
                        $statBars = collect([
                            ['label' => 'Ortalama puan', 'value' => $media->average_score, 'max' => 100, 'suffix' => '%', 'icon' => 'fa-solid fa-star'],
                            ['label' => 'Genel puan', 'value' => $media->mean_score, 'max' => 100, 'suffix' => '%', 'icon' => 'fa-solid fa-chart-simple'],
                            ['label' => 'Popülerlik', 'value' => $media->popularity, 'max' => max(1, (int) max($media->popularity ?? 0, $media->favourites ?? 0)), 'suffix' => '', 'icon' => 'fa-solid fa-fire'],
                            ['label' => 'Favori', 'value' => $media->favourites, 'max' => max(1, (int) max($media->popularity ?? 0, $media->favourites ?? 0)), 'suffix' => '', 'icon' => 'fa-solid fa-heart'],
                        ])->filter(fn ($stat) => filled($stat['value']))->values();
                    @endphp
                    @if($statBars->isNotEmpty())
                        <div class="nozu-stat-graph">
                            @foreach($statBars as $stat)
                                @php
                                    $percent = min(100, max(4, round(((float) $stat['value'] / max(1, (float) $stat['max'])) * 100)));
                                @endphp
                                <div class="nozu-stat-bar" style="--value: {{ $percent }}%">
                                    <span class="stat-bar-icon"><i class="{{ $stat['icon'] }}"></i></span>
                                    <span class="stat-bar-label">{{ $stat['label'] }}</span>
                                    <strong>{{ is_numeric($stat['value']) ? number_format((float) $stat['value'], 0, ',', '.') : $stat['value'] }}{{ $stat['suffix'] }}</strong>
                                    <span class="stat-bar-track"><span></span></span>
                                </div>
                            @endforeach
                        </div>
                    @endif
                    <div class="nozu-stat-line">
                        @if($media->average_score)<span>Ortalama {{ $media->average_score }}%</span>@endif
                        @if($media->mean_score)<span>Genel {{ $media->mean_score }}%</span>@endif
                        @if($media->popularity)<span>Popülerlik {{ number_format($media->popularity, 0, ',', '.') }}</span>@endif
                        @if($media->favourites)<span>Favori {{ number_format($media->favourites, 0, ',', '.') }}</span>@endif
                    </div>
                </section>

                <section class="nozu-content-block comments-section nozu-tab-panel" id="comments" data-tab-panel="comments">
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
            </main>

            <aside class="nozu-right-rail">
                <section class="nozu-report-card">
                    @auth
                        <details>
                            <summary><i class="fa-regular fa-flag"></i> Rapor et</summary>
                            <form method="post" action="{{ route('media.report', $media) }}">
                                @csrf
                                <label>
                                    <span>Sorun türü</span>
                                    <select name="reason" required>
                                        <option value="wrong_info">Yanlış anime bilgileri.</option>
                                        <option value="wrong_images">Yanlış Anime görselleri</option>
                                        <option value="wrong_summary">Yanlış Anime özeti</option>
                                        <option value="translation_error">Çeviri Hatası</option>
                                        <option value="translation_help">Çeviri konusunda yardımcı olmak istiyorum.</option>
                                        <option value="other">Diğer</option>
                                    </select>
                                </label>
                                <label>
                                    <span>Açıklama</span>
                                    <textarea name="details" rows="4" maxlength="1000" placeholder="Kısa bir açıklama yaz..."></textarea>
                                </label>
                                <button type="submit">Rapor gönder</button>
                            </form>
                        </details>
                    @else
                        <a href="{{ route('login') }}"><i class="fa-regular fa-flag"></i> Rapor etmek için giriş yap</a>
                    @endauth
                </section>

                <section class="nozu-details-card">
                    <h2>Detaylar</h2>
                    @if($media->title_english)<p><i class="fa-solid fa-language"></i><span>İngilizce</span><strong>{{ $media->title_english }}</strong></p>@endif
                    @if($media->title_native)<p><i class="fa-solid fa-torii-gate"></i><span>Japonca</span><strong>{{ $media->title_native }}</strong></p>@endif
                    @if($media->format)<p><i class="fa-solid fa-layer-group"></i><span>Tür</span><strong>{{ $media->format }}</strong></p>@endif
                    @if($media->episodes)<p><i class="fa-solid fa-clapperboard"></i><span>Bölümler</span><strong>{{ $media->episodes }}</strong></p>@endif
                    @if($media->chapters)<p><i class="fa-solid fa-book-open"></i><span>Bölümler</span><strong>{{ $media->chapters }}</strong></p>@endif
                    @if($media->volumes)<p><i class="fa-solid fa-book"></i><span>Cilt</span><strong>{{ $media->volumes }}</strong></p>@endif
                    @if($media->status)<p><i class="fa-solid fa-signal"></i><span>Durum</span><strong>{{ $media->status }}</strong></p>@endif
                    @if($media->start_date)<p><i class="fa-solid fa-calendar-day"></i><span>Yayınlandı</span><strong>{{ $media->start_date->format('d.m.Y') }}</strong></p>@endif
                    @if($media->season || $media->season_year)<p><i class="fa-solid fa-cloud-sun"></i><span>Sezon</span><strong>{{ trim(($media->season ?? '').' '.($media->season_year ?? '')) }}</strong></p>@endif
                    @if($media->source)<p><i class="fa-solid fa-seedling"></i><span>Kaynak</span><strong>{{ $media->source }}</strong></p>@endif
                    @if($media->country_of_origin)<p><i class="fa-solid fa-earth-asia"></i><span>Ülke</span><strong>{{ $media->country_of_origin }}</strong></p>@endif
                </section>

                @if($related->isNotEmpty())
                    <section class="nozu-details-card" id="recommendations">
                        <h2>Bu seriden daha fazla</h2>
                        <div class="nozu-mini-posters">
                            @foreach($related->take(4) as $item)
                                @if($item->cover_image)
                                    <a href="{{ route('media.show', ['type' => $item->type, 'media' => $item]) }}">
                                        <x-responsive-image
                                            :src="$item->cover_image"
                                            :alt="$item->title"
                                            sizes="64px"
                                            :widths="[96, 160]"
                                        />
                                    </a>
                                @endif
                            @endforeach
                        </div>
                    </section>
                @endif
            </aside>
        </div>
    </div>

    <script>
        (() => {
            const script = document.currentScript;
            const root = script?.previousElementSibling?.matches('.nozu-detail-page')
                ? script.previousElementSibling
                : document.querySelector('.nozu-detail-page');
            if (!root) {
                return;
            }

            const links = Array.from(root.querySelectorAll('[data-tab-link]'));
            const panels = Array.from(root.querySelectorAll('[data-tab-panel]'));

            const activate = (id, updateHash = true) => {
                const panel = root.querySelector(`[data-tab-panel="${id}"]`);
                if (!panel) {
                    return;
                }

                links.forEach((link) => {
                    link.classList.toggle('is-active', link.dataset.tabLink === id);
                });

                panels.forEach((item) => {
                    item.classList.toggle('is-active', item.dataset.tabPanel === id);
                });

                if (updateHash) {
                    history.replaceState(null, '', `#${id}`);
                }
            };

            links.forEach((link) => {
                link.addEventListener('click', (event) => {
                    const id = link.dataset.tabLink;
                    if (!id) {
                        return;
                    }

                    event.preventDefault();
                    activate(id);
                });
            });

            const initial = window.location.hash.replace('#', '');
            if (initial) {
                activate(initial, false);
            }
        })();
    </script>
@endsection
