@extends('layouts.app')

@section('content')
    @php
        $statusLabels = [
            'watching' => 'İzliyor',
            'reading' => 'Okuyor',
            'completed' => 'Tamamladı',
            'dropped' => 'Bıraktı',
            'planned' => 'Planlıyor',
        ];
        $baseRoute = $isOwner ? route('profile.list') : route('profile.public-list', $owner->username);
    @endphp

    <section class="directory-hero library-hero">
        <div>
            <p class="eyebrow">{{ $isOwner ? 'Profil' : '@'.$owner->username }}</p>
            <h1>{{ $isOwner ? 'İzleme listem' : $owner->username.' izleme listesi' }}</h1>
            <p>Anime ve mangaları durumlarına göre kitaplık görünümünde keşfet.</p>
        </div>

        <div class="library-share-card">
            <span><i class="fa-solid fa-share-nodes"></i> Paylaşılabilir link</span>
            <div class="share-link-row">
                <input id="share-url" type="text" value="{{ $shareUrl }}" readonly>
                <button class="button ghost" type="button" data-copy-share>
                    <i class="fa-regular fa-copy"></i> Kopyala
                </button>
            </div>
        </div>
    </section>

    <form class="library-toolbar" method="get" action="{{ $baseRoute }}">
        <label>
            <i class="fa-solid fa-magnifying-glass"></i>
            <input type="search" name="q" value="{{ $query }}" placeholder="Listede anime veya manga ara...">
        </label>
        @if($activeStatus)
            <input type="hidden" name="status" value="{{ $activeStatus }}">
        @endif
        <button class="button primary"><i class="fa-solid fa-filter"></i> Ara</button>
        @if($query || $activeStatus)
            <a class="button ghost" href="{{ $baseRoute }}">Temizle</a>
        @endif
    </form>

    <nav class="library-status-tabs" aria-label="Liste durumu filtresi">
        <a class="{{ $activeStatus ? '' : 'active' }}" href="{{ $query ? $baseRoute.'?'.http_build_query(['q' => $query]) : $baseRoute }}">
            <i class="fa-solid fa-border-all"></i> Tümü
        </a>
        @foreach($statusLabels as $status => $label)
            <a class="{{ $activeStatus === $status ? 'active' : '' }}" href="{{ $baseRoute.'?'.http_build_query(array_filter(['q' => $query, 'status' => $status])) }}">
                <i class="fa-solid {{ $status === 'watching' ? 'fa-eye' : ($status === 'reading' ? 'fa-book-open' : ($status === 'completed' ? 'fa-circle-check' : ($status === 'dropped' ? 'fa-circle-xmark' : 'fa-clock'))) }}"></i>
                {{ $label }}
            </a>
        @endforeach
    </nav>

    <div class="library-grid">
        @forelse($items as $entry)
            <article class="library-card">
                <a href="{{ route('media.show', ['type' => $entry->media->type, 'media' => $entry->media]) }}">
                    @if($entry->media->cover_image)
                        <x-responsive-image
                            :src="$entry->media->cover_image"
                            :alt="$entry->media->title"
                            sizes="96px"
                            :widths="[160, 240]"
                        />
                    @endif
                    <div>
                        <span>{{ $statusLabels[$entry->status] ?? $entry->status }}</span>
                        <strong>{{ $entry->media->title }}</strong>
                        <small>{{ $entry->media->type === 'anime' ? 'Anime' : 'Manga' }}</small>
                    </div>
                </a>
                @if($isOwner)
                    <form method="post" action="{{ route('media.list.remove', $entry->media) }}">
                        @csrf
                        @method('DELETE')
                        <button class="button ghost"><i class="fa-solid fa-trash"></i> Kaldır</button>
                    </form>
                @endif
            </article>
        @empty
            <p class="muted library-empty">{{ $query || $activeStatus ? 'Filtrene uygun içerik bulunamadı.' : 'Henüz listede içerik yok.' }}</p>
        @endforelse
    </div>

    {{ $items->links() }}

    <script>
        document.querySelector('[data-copy-share]')?.addEventListener('click', async () => {
            const input = document.getElementById('share-url');
            if (! input) return;

            try {
                await navigator.clipboard.writeText(input.value);
            } catch (error) {
                input.select();
                document.execCommand('copy');
            }
        });
    </script>
@endsection
