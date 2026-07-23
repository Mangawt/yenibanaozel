<article class="media-card flat-media-card">
    <a href="{{ route('media.show', ['type' => $item->type, 'media' => $item]) }}">
        <div class="poster">
            @if($item->cover_image)
                <x-responsive-image
                    :src="$item->cover_image"
                    :alt="$item->title"
                    sizes="(max-width: 520px) 46vw, (max-width: 900px) 30vw, 168px"
                />
            @endif
            @if($item->average_score)
                <span class="score-badge"><i class="fa-solid fa-star"></i> {{ number_format($item->average_score / 10, 1) }}</span>
            @endif
        </div>
        <div class="media-card-body">
            <strong>{{ $item->title }}</strong>
            <span>{{ $item->format ?: strtoupper($item->type) }} @if($item->start_year) &middot; {{ $item->start_year }} @endif</span>
        </div>
    </a>
</article>
