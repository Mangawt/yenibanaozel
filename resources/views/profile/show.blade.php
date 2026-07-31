@extends('layouts.app')

@section('content')
    @php
        $statusLabels = [
            'favorite' => 'Favorilere ekledi',
            'watching' => 'İzliyor',
            'reading' => 'Okuyor',
            'paused' => 'Duraklatıldı',
            'completed' => 'Tamamladı',
            'dropped' => 'Bıraktı',
            'planned' => 'Planlıyor',
        ];
        $socialIcons = [
            'instagram' => 'fa-brands fa-instagram',
            'facebook' => 'fa-brands fa-facebook-f',
            'discord' => 'fa-brands fa-discord',
            'x' => 'fa-brands fa-x-twitter',
            'youtube' => 'fa-brands fa-youtube',
            'website' => 'fa-solid fa-globe',
        ];
        $socialLabels = [
            'instagram' => 'Instagram',
            'facebook' => 'Facebook',
            'discord' => 'Discord',
            'x' => 'X',
            'youtube' => 'YouTube',
            'website' => 'Web',
        ];
        $avatarUrl = $user->avatar_path ? app(\App\Services\UserMediaStorage::class)->url($user->avatar_path) : null;
        $bannerUrl = $user->banner_path ? app(\App\Services\UserMediaStorage::class)->url($user->banner_path) : null;
        $topSpectrum = ($animeStats['spectrum'] ?? [])[0] ?? null;
    @endphp

    <main class="nozu-profile-page nozu-profile-clean">
        <section class="profile-clean-hero" @if($bannerUrl) style="--profile-banner: url('{{ $bannerUrl }}')" @endif>
            <div class="profile-clean-cover"></div>
            <div class="profile-clean-head">
                <div class="profile-clean-avatar">
                    @if($avatarUrl)
                        <img src="{{ $avatarUrl }}" alt="{{ $user->username }}">
                    @else
                        <span>{{ mb_substr($user->username ?: 'N', 0, 1) }}</span>
                    @endif
                </div>

                <div class="profile-clean-title">
                    <span>nozu.me profili</span>
                    <h1>{{ '@'.$user->username }}</h1>
                    <p>{{ $user->bio ?: 'Bu profil henüz bir açıklama eklemedi.' }}</p>
                </div>

                <div class="profile-clean-actions">
                    @auth
                        @unless(auth()->id() === $user->id)
                            <form method="post" action="{{ route('profile.follow', $user) }}">
                                @csrf
                                <button class="button primary"><i class="fa-solid fa-user-plus"></i> {{ $isFollowing ? 'Takibi bırak' : 'Takip et' }}</button>
                            </form>
                            <form method="post" action="{{ route('profile.report', $user) }}">
                                @csrf
                                <button class="button danger"><i class="fa-regular fa-flag"></i> Şikayet et</button>
                            </form>
                        @endunless
                    @endauth
                </div>
            </div>
        </section>

        <section class="profile-clean-quick">
            <a href="{{ route('profile.followers', $user->username) }}">
                <strong>{{ number_format($user->followers_count, 0, ',', '.') }}</strong>
                <span>Takipçi</span>
            </a>
            <a href="{{ route('profile.following', $user->username) }}">
                <strong>{{ number_format($user->following_count, 0, ',', '.') }}</strong>
                <span>Takip edilen</span>
            </a>
            <div>
                <strong>{{ number_format($favoriteAnimeCount, 0, ',', '.') }}</strong>
                <span>Favori anime</span>
            </div>
            <div>
                <strong>{{ number_format($favoriteMangaCount, 0, ',', '.') }}</strong>
                <span>Favori manga</span>
            </div>
        </section>

        <section class="profile-clean-grid">
            <aside class="profile-clean-sidebar">
                <section class="profile-clean-card">
                    <div class="profile-clean-card-head">
                        <h2><i class="fa-solid fa-bolt"></i> Aktiviteler</h2>
                        <span>Son {{ $activities->count() }}</span>
                    </div>

                    <div class="profile-clean-activities">
                        @forelse($activities as $activity)
                            <a href="{{ route('media.show', ['type' => $activity['media']->type, 'media' => $activity['media']]) }}{{ $activity['label'] ? '#comments' : '' }}">
                                <i class="fa-solid {{ $activity['label'] ? 'fa-comment' : 'fa-layer-group' }}"></i>
                                <span>{{ $activity['label'] ?: ($statusLabels[$activity['status']] ?? $activity['status']) }}</span>
                                <strong>{{ $activity['media']->title }}</strong>
                            </a>
                        @empty
                            <p class="muted">Henüz aktivite yok.</p>
                        @endforelse
                    </div>
                </section>

                <section class="profile-clean-card">
                    <div class="profile-clean-card-head">
                        <h2><i class="fa-solid fa-share-nodes"></i> Sosyal</h2>
                        <span>Bağlantılar</span>
                    </div>

                    @if(count($user->social_links ?? []))
                        <div class="profile-clean-socials">
                            @foreach($user->social_links as $platform => $value)
                                @if($platform === 'discord')
                                    <span title="{{ $value }}"><i class="{{ $socialIcons[$platform] ?? 'fa-solid fa-link' }}"></i>{{ $socialLabels[$platform] ?? ucfirst($platform) }}</span>
                                @else
                                    <a href="{{ $value }}" target="_blank" rel="noopener" title="{{ $socialLabels[$platform] ?? ucfirst($platform) }}">
                                        <i class="{{ $socialIcons[$platform] ?? 'fa-solid fa-link' }}"></i>{{ $socialLabels[$platform] ?? ucfirst($platform) }}
                                    </a>
                                @endif
                            @endforeach
                        </div>
                    @else
                        <p class="muted">Henüz sosyal bağlantı eklenmedi.</p>
                    @endif
                </section>
            </aside>

            <div class="profile-clean-main">
                <section class="profile-clean-card profile-clean-stats">
                    <div class="profile-clean-card-head">
                        <h2><i class="fa-solid fa-chart-simple"></i> Anime İstatistikleri</h2>
                        @if($animeStats['has_stats'] ?? false)
                            <span>Liste ilerlemesine göre</span>
                        @endif
                    </div>

                    @if($animeStats['has_stats'] ?? false)
                        <div class="profile-clean-metrics">
                            <article>
                                <span>Tamamlanan anime</span>
                                <strong>{{ number_format($animeStats['summary']['completed_anime_count'] ?? 0, 0, ',', '.') }}</strong>
                            </article>
                            <article>
                                <span>Bölüm</span>
                                <strong>{{ number_format($animeStats['summary']['watched_episodes'] ?? 0, 0, ',', '.') }}</strong>
                            </article>
                            <article>
                                <span>Süre</span>
                                <strong>{{ $animeStats['summary']['watch_time_label'] ?? '0 dakika' }}</strong>
                            </article>
                            <article>
                                <span>Puan</span>
                                <strong>{{ $animeStats['summary']['average_score'] ?? '-' }}</strong>
                            </article>
                        </div>

                        @php
                            $spectrum = collect($animeStats['spectrum'] ?? [])->values();
                            $spectrumColors = ['#ffb703', '#fb8500', '#ff2f7d', '#b565d9', '#06b6d4', '#59629b'];
                            $spectrumTotal = max(1, (int) $spectrum->sum(fn ($genre) => (int) ($genre['percent'] ?? 0)));
                            $spectrumCursor = 0;
                            $spectrumSegments = [];

                            foreach ($spectrum as $index => $genre) {
                                $color = $spectrumColors[$index % count($spectrumColors)];
                                $slice = ((int) ($genre['percent'] ?? 0) / $spectrumTotal) * 100;
                                $end = $index === $spectrum->count() - 1 ? 100 : min(100, $spectrumCursor + $slice);
                                $spectrumSegments[] = $color.' '.$spectrumCursor.'% '.$end.'%';
                                $spectrumCursor = $end;
                            }

                            $spectrumGradient = $spectrumSegments
                                ? 'linear-gradient(90deg, '.implode(',', $spectrumSegments).')'
                                : 'linear-gradient(90deg, var(--border-soft), var(--border-soft))';
                        @endphp

                        <div class="profile-clean-stats-grid">
                            <div class="profile-clean-spectrum profile-clean-genre-overview" style="--genre-stack: {{ $spectrumGradient }};">
                                <h3>Tür dağılımı</h3>
                                <div class="profile-clean-genre-pills">
                                    @forelse($spectrum as $index => $genre)
                                        <article style="--genre-color: {{ $spectrumColors[$index % count($spectrumColors)] }};">
                                            <strong>{{ $genre['name'] }}</strong>
                                            <span>{{ $genre['percent'] }}<small>%</small></span>
                                        </article>
                                    @empty
                                        <p class="muted">Tür dağılımı için yeterli veri yok.</p>
                                    @endforelse
                                </div>
                                @if($spectrum->isNotEmpty())
                                    <div class="profile-clean-genre-stack"></div>
                                @endif
                            </div>

                            <div class="profile-clean-identity">
                                <h3>İzleme kimliği</h3>
                                @if(! empty($animeStats['identity']['dominant_genre']))
                                    <p><span>En baskın tür</span><strong>{{ $animeStats['identity']['dominant_genre'] }}</strong></p>
                                @endif
                                @if(! empty($animeStats['identity']['top_format']))
                                    <p><span>Format</span><strong>{{ $animeStats['identity']['top_format'] }}</strong></p>
                                @endif
                                @if(! empty($animeStats['identity']['top_studio']))
                                    <p><span>Stüdyo</span><strong>{{ $animeStats['identity']['top_studio'] }}</strong></p>
                                @endif
                                <p><span>Son 30 gün</span><strong>{{ number_format($animeStats['identity']['recent_activity_count'] ?? 0, 0, ',', '.') }} hareket</strong></p>
                                <p><span>Anime yorumları</span><strong>{{ number_format($animeStats['social']['anime_comments_count'] ?? 0, 0, ',', '.') }}</strong></p>
                                <p><span>Pozitif yorum skoru</span><strong>{{ number_format($animeStats['social']['positive_comment_score'] ?? 0, 0, ',', '.') }}</strong></p>
                            </div>
                        </div>
                    @else
                        <div class="profile-clean-empty">
                            <i class="fa-solid fa-chart-simple"></i>
                            <strong>Anime istatistikleri henüz oluşmadı.</strong>
                            <p>Listeye anime ekledikçe izleme alışkanlıkların burada görünecek.</p>
                        </div>
                    @endif
                </section>

                <section class="profile-clean-card">
                    <div class="profile-clean-card-head">
                        <h2><i class="fa-solid fa-heart"></i> Favori animeler</h2>
                        <span>{{ number_format($favoriteAnimeCount, 0, ',', '.') }} kayıt</span>
                    </div>
                    <div class="profile-clean-posters">
                        @forelse($favoritesAnime as $item)
                            @include('components.media-card', ['item' => $item])
                        @empty
                            <p class="muted">Henüz favori anime yok.</p>
                        @endforelse
                        @if($favoriteAnimeCount > $favoritesAnime->count())
                            <a class="profile-clean-more" href="{{ route('profile.list') }}">
                                <strong>+{{ $favoriteAnimeCount - $favoritesAnime->count() }}</strong>
                                <span>Tümünü gör</span>
                            </a>
                        @endif
                    </div>
                </section>

                <section class="profile-clean-card">
                    <div class="profile-clean-card-head">
                        <h2><i class="fa-solid fa-bookmark"></i> Favori mangalar</h2>
                        <span>{{ number_format($favoriteMangaCount, 0, ',', '.') }} kayıt</span>
                    </div>
                    <div class="profile-clean-posters">
                        @forelse($favoritesManga as $item)
                            @include('components.media-card', ['item' => $item])
                        @empty
                            <p class="muted">Henüz favori manga yok.</p>
                        @endforelse
                        @if($favoriteMangaCount > $favoritesManga->count())
                            <a class="profile-clean-more" href="{{ route('profile.list') }}">
                                <strong>+{{ $favoriteMangaCount - $favoritesManga->count() }}</strong>
                                <span>Tümünü gör</span>
                            </a>
                        @endif
                    </div>
                </section>

                <section class="profile-clean-card">
                    <div class="profile-clean-card-head">
                        <h2><i class="fa-solid fa-layer-group"></i> İzleme listesi</h2>
                        @auth
                            @if(auth()->id() === $user->id)
                                <a class="button ghost" href="{{ route('profile.list') }}">Yönet</a>
                            @endif
                        @endauth
                    </div>
                    <div class="profile-clean-shelf">
                        @forelse($watchList as $entry)
                            <article>
                                <a href="{{ route('media.show', ['type' => $entry->media->type, 'media' => $entry->media]) }}">
                                    @if($entry->media->cover_image)
                                        <x-responsive-image
                                            :src="$entry->media->cover_image"
                                            :alt="$entry->media->title"
                                            sizes="120px"
                                            :widths="[160, 240]"
                                        />
                                    @endif
                                    <span>{{ $statusLabels[$entry->status] ?? $entry->status }}</span>
                                    <strong>{{ $entry->media->title }}</strong>
                                </a>
                            </article>
                        @empty
                            <p class="muted">İzleme listesinde içerik yok.</p>
                        @endforelse
                    </div>
                </section>
            </div>
        </section>
    </main>
@endsection
