@if ($paginator->hasPages())
    <nav class="nozu-pagination" role="navigation" aria-label="Sayfalandırma">
        <div class="pagination-summary">
            {{ $paginator->firstItem() ?? 0 }}-{{ $paginator->lastItem() ?? 0 }} / {{ $paginator->total() }} sonuç
        </div>

        <div class="pagination-controls">
            @if ($paginator->onFirstPage())
                <span class="page-button disabled" aria-disabled="true">Önceki</span>
            @else
                <a class="page-button" href="{{ $paginator->previousPageUrl() }}" rel="prev">Önceki</a>
            @endif

            <div class="page-numbers">
                @foreach ($elements as $element)
                    @if (is_string($element))
                        <span class="page-button dots" aria-hidden="true">{{ $element }}</span>
                    @endif

                    @if (is_array($element))
                        @foreach ($element as $page => $url)
                            @if ($page == $paginator->currentPage())
                                <span class="page-button active" aria-current="page">{{ $page }}</span>
                            @else
                                <a class="page-button" href="{{ $url }}">{{ $page }}</a>
                            @endif
                        @endforeach
                    @endif
                @endforeach
            </div>

            @if ($paginator->hasMorePages())
                <a class="page-button" href="{{ $paginator->nextPageUrl() }}" rel="next">Sonraki</a>
            @else
                <span class="page-button disabled" aria-disabled="true">Sonraki</span>
            @endif
        </div>
    </nav>
@endif
