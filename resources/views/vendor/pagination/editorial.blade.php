@if ($paginator->hasPages())
    <nav role="navigation" aria-label="{{ __('Pagination Navigation') }}" class="editorial-pagination">
        <p class="editorial-pagination__summary">
            Page {{ $paginator->currentPage() }} of {{ $paginator->lastPage() }}
        </p>

        <div class="editorial-pagination__links">
            @if (! $paginator->onFirstPage())
                <a class="editorial-pagination__link" href="{{ $paginator->previousPageUrl() }}" rel="prev">Previous</a>
            @endif

            @foreach ($elements as $element)
                @if (is_string($element))
                    <span class="editorial-pagination__dots">{{ $element }}</span>
                @endif

                @if (is_array($element))
                    @foreach ($element as $page => $url)
                        @if ($page == $paginator->currentPage())
                            <span class="editorial-pagination__link is-current" aria-current="page">{{ $page }}</span>
                        @else
                            <a class="editorial-pagination__link" href="{{ $url }}" aria-label="{{ __('Go to page :page', ['page' => $page]) }}">{{ $page }}</a>
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
