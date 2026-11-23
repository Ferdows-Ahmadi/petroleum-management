@if ($paginator->hasPages())
    <nav class="paginationx" role="navigation" aria-label="{{ __('pagination.navigation') }}">
        @if ($paginator->onFirstPage())
            <span class="page-linkx disabled">{{ __('pagination.previous') }}</span>
        @else
            <a class="page-linkx" href="{{ $paginator->previousPageUrl() }}" rel="prev">{{ __('pagination.previous') }}</a>
        @endif
        <span class="page-current">{{ $paginator->currentPage() }} / {{ $paginator->lastPage() }}</span>
        @if ($paginator->hasMorePages())
            <a class="page-linkx" href="{{ $paginator->nextPageUrl() }}" rel="next">{{ __('pagination.next') }}</a>
        @else
            <span class="page-linkx disabled">{{ __('pagination.next') }}</span>
        @endif
    </nav>
@endif
