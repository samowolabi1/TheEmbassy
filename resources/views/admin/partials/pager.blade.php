{{-- Simple previous/next pagination for admin lists. --}}
@if ($paginator->hasPages())
    <nav class="pager" aria-label="Pages">
        <span>Showing {{ $paginator->firstItem() }}–{{ $paginator->lastItem() }} of {{ number_format($paginator->total()) }}</span>
        <span class="pager__links">
            @if ($paginator->onFirstPage())
                <span class="btn btn--ghost btn--xs" aria-disabled="true" style="opacity: 0.5">Previous</span>
            @else
                <a href="{{ $paginator->previousPageUrl() }}" class="btn btn--ghost btn--xs" rel="prev">Previous</a>
            @endif
            @if ($paginator->hasMorePages())
                <a href="{{ $paginator->nextPageUrl() }}" class="btn btn--ghost btn--xs" rel="next">Next</a>
            @else
                <span class="btn btn--ghost btn--xs" aria-disabled="true" style="opacity: 0.5">Next</span>
            @endif
        </span>
    </nav>
@endif
