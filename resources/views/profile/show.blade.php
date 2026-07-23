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
    @endphp

    <section class="profile-public profile-v2">
        <div class="profile-avatar large">
            @if($user->avatar_path)
                <img src="{{ asset('storage/'.$user->avatar_path) }}" alt="{{ $user->username }}">
            @else
                <span>{{ mb_substr($user->username ?: 'N', 0, 1) }}</span>
            @endif
        </div>
        <div class="profile-identity">
            <p class="eyebrow">nozu.me profili</p>
            <h1>{{ '@'.$user->username }}</h1>
            @if($user->bio)
                <p>{{ $user->bio }}</p>
            @endif
        </div>
        <div class="profile-actions">
            @auth
                @unless(auth()->id() === $user->id)
                    <form method="post" action="{{ route('profile.report', $user) }}">
                        @csrf
                        <button class="button danger"><i class="fa-regular fa-flag"></i> Şikayet et</button>
                    </form>
                    <form method="post" action="{{ route('profile.follow', $user) }}">
                        @csrf
                        <button class="button primary"><i class="fa-solid fa-user-plus"></i> {{ $isFollowing ? 'Takibi bırak' : 'Takip et' }}</button>
                    </form>
                @endunless
            @endauth
        </div>
    </section>

    <section class="profile-dashboard">
        <aside class="profile-activity">
            <h2><i class="fa-solid fa-bolt"></i> Aktiviteler</h2>
            @forelse($activities as $activity)
                <a class="activity-row" href="{{ route('media.show', ['type' => $activity['media']->type, 'media' => $activity['media']]) }}{{ $activity['label'] ? '#comments' : '' }}">
                    <span>{{ $activity['label'] ?: ($statusLabels[$activity['status']] ?? $activity['status']) }}</span>
                    <strong>{{ $activity['media']->title }}</strong>
                </a>
            @empty
                <p class="muted">Henüz aktivite yok.</p>
            @endforelse
        </aside>

        <div class="profile-lists">
            <section class="profile-list-panel">
                <h2><i class="fa-solid fa-heart"></i> Favori animeler</h2>
                <div class="mini-poster-row tiny-cards">
                    @forelse($favoritesAnime as $item)
                        @include('components.media-card', ['item' => $item])
                    @empty
                        <p class="muted">Henüz favori anime yok.</p>
                    @endforelse
                    @if($favoriteAnimeCount > $favoritesAnime->count())
                        <a class="more-favorites-card" href="{{ route('profile.list') }}">
                            <strong>+{{ $favoriteAnimeCount - $favoritesAnime->count() }}</strong>
                            <span>Tümünü gör</span>
                        </a>
                    @endif
                </div>
            </section>

            <section class="profile-list-panel">
                <h2><i class="fa-solid fa-bookmark"></i> Favori mangalar</h2>
                <div class="mini-poster-row tiny-cards">
                    @forelse($favoritesManga as $item)
                        @include('components.media-card', ['item' => $item])
                    @empty
                        <p class="muted">Henüz favori manga yok.</p>
                    @endforelse
                    @if($favoriteMangaCount > $favoritesManga->count())
                        <a class="more-favorites-card" href="{{ route('profile.list') }}">
                            <strong>+{{ $favoriteMangaCount - $favoritesManga->count() }}</strong>
                            <span>Tümünü gör</span>
                        </a>
                    @endif
                </div>
            </section>

            <section class="profile-list-panel watch-shelf-panel">
                <div class="section-head-row">
                    <h2><i class="fa-solid fa-layer-group"></i> İzleme listesi</h2>
                    @auth
                        @if(auth()->id() === $user->id)
                            <a class="button ghost" href="{{ route('profile.list') }}">Yönet</a>
                        @endif
                    @endauth
                </div>
                <div class="bookshelf-row">
                    @forelse($watchList as $entry)
                        <article class="shelf-item">
                            <a href="{{ route('media.show', ['type' => $entry->media->type, 'media' => $entry->media]) }}">
                                @if($entry->media->cover_image)
                                    <x-responsive-image
                                        :src="$entry->media->cover_image"
                                        :alt="$entry->media->title"
                                        sizes="110px"
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

            <section class="profile-social-panel">
                <div>
                    <h2><i class="fa-solid fa-share-nodes"></i> Sosyal</h2>
                    @if(count($user->social_links ?? []))
                        <div class="profile-social-links icon-socials">
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
                </div>
                <div class="social-counts">
                    <a href="{{ route('profile.followers', $user->username) }}"><i class="fa-solid fa-users"></i><strong>{{ $user->followers_count }}</strong> takipçi</a>
                    <a href="{{ route('profile.following', $user->username) }}"><i class="fa-solid fa-user-check"></i><strong>{{ $user->following_count }}</strong> takip edilen</a>
                </div>
            </section>
        </div>
    </section>
@endsection
