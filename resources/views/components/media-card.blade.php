<article class="media-card">
    <a href="{{ route('media.show', ['type' => $item->type, 'media' => $item]) }}">
        <div class="poster">
            @if($item->cover_image)
                <img src="{{ $item->cover_image }}" alt="{{ $item->title }}">
            @endif
            @if($item->average_score)
                <span class="score-badge"><i class="fa-solid fa-star"></i> {{ number_format($item->average_score / 10, 1) }}</span>
            @endif
            <span class="poster-fade"></span>
        </div>
        <div class="media-card-body">
            <strong>{{ $item->title }}</strong>
            <span>{{ $item->format ?: strtoupper($item->type) }} @if($item->start_year) · {{ $item->start_year }} @endif</span>
        </div>
    </a>
</article>
