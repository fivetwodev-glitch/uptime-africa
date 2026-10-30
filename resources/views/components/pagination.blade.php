@if ($paginator->hasPages())
    <nav class="flex items-center justify-between gap-4 border-t border-slate-100 px-5 py-3 text-sm" aria-label="Pagination">
        <p class="text-slate-500">
            {{ $paginator->firstItem() }}–{{ $paginator->lastItem() }} sur {{ $paginator->total() }}
        </p>
        <div class="flex gap-2">
            @if ($paginator->onFirstPage())
                <span class="btn-secondary btn-sm pointer-events-none opacity-40">Précédent</span>
            @else
                <a href="{{ $paginator->previousPageUrl() }}" rel="prev" class="btn-secondary btn-sm">Précédent</a>
            @endif
            @if ($paginator->hasMorePages())
                <a href="{{ $paginator->nextPageUrl() }}" rel="next" class="btn-secondary btn-sm">Suivant</a>
            @else
                <span class="btn-secondary btn-sm pointer-events-none opacity-40">Suivant</span>
            @endif
        </div>
    </nav>
@endif
