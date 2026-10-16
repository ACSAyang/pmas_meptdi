@if ($paginator->hasPages() || $paginator->total() > 0)
<nav class="pagination" aria-label="Pagination">
    <div>Showing <b>{{ $paginator->firstItem() ?? 0 }}</b>–<b>{{ $paginator->lastItem() ?? 0 }}</b> of <b>{{ $paginator->total() }}</b></div>
    @if ($paginator->hasPages())
    <div class="pages">
        @if ($paginator->onFirstPage())<span class="disabled"><x-icon name="chevron-left" size="sm"/></span>@else<a href="{{ $paginator->previousPageUrl() }}" rel="prev" aria-label="Previous"><x-icon name="chevron-left" size="sm"/></a>@endif
        @foreach ($elements as $element)
            @if (is_string($element))<span class="disabled">{{ $element }}</span>@endif
            @if (is_array($element))
                @foreach ($element as $page => $url)
                    @if ($page == $paginator->currentPage())<span class="current">{{ $page }}</span>@else<a href="{{ $url }}">{{ $page }}</a>@endif
                @endforeach
            @endif
        @endforeach
        @if ($paginator->hasMorePages())<a href="{{ $paginator->nextPageUrl() }}" rel="next" aria-label="Next"><x-icon name="chevron-right" size="sm"/></a>@else<span class="disabled"><x-icon name="chevron-right" size="sm"/></span>@endif
    </div>
    @endif
</nav>
@endif
