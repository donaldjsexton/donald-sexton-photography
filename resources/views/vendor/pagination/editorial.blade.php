@if ($paginator->hasPages())
    <nav role="navigation" aria-label="Pagination Navigation" class="editorial-pagination">
        <p class="editorial-pagination__summary">
            Page {{ $paginator->currentPage() }} of {{ $paginator->lastPage() }}
        </p>

        <div class="editorial-pagination__links">
            @unless ($paginator->onFirstPage())
                <a class="editorial-pagination__link" href="{{ $paginator->previousPageUrl() }}" rel="prev">Previous</a>
            @endunless

            @foreach ($elements as $element)
                @if (is_string($element))
                    <span class="editorial-pagination__dots">{{ $element }}</span>
                @endif

                @if (is_array($element))
                    @foreach ($element as $page => $url)
                        @if ($page == $paginator->currentPage())
                            <span class="editorial-pagination__link is-current">{{ $page }}</span>
                        @else
                            <a class="editorial-pagination__link" href="{{ $url }}">{{ $page }}</a>
                        @endif
                    @endforeach
                @endif
            @endforeach

            @if ($paginator->hasMorePages())
                <a class="editorial-pagination__link" href="{{ $paginator->nextPageUrl() }}" rel="next">Next</a>
            @endif
        </div>
    </nav>
@endif
