@if ($paginator->total() > 0)
    <nav role="navigation" aria-label="Paginación" class="flex flex-wrap items-center justify-between gap-3 text-xs text-slate-500">
        <p>{{ $paginator->firstItem() }}-{{ $paginator->lastItem() }} de {{ $paginator->total() }} resultados</p>

        @if ($paginator->hasPages())
            <ul class="flex items-center gap-1">
                <li>
                    @if ($paginator->onFirstPage())
                        <span class="grid size-8 place-items-center text-slate-300" aria-disabled="true"><x-icon name="chevron-left" /></span>
                    @else
                        <a href="{{ $paginator->previousPageUrl() }}" rel="prev" aria-label="Página anterior" class="grid size-8 place-items-center rounded-lg hover:bg-slate-100"><x-icon name="chevron-left" /></a>
                    @endif
                </li>

                @foreach ($elements as $element)
                    @if (is_string($element))
                        <li class="hidden sm:block"><span class="grid size-8 place-items-center">{{ $element }}</span></li>
                    @endif

                    @if (is_array($element))
                        @foreach ($element as $page => $url)
                            @if ($page == $paginator->currentPage())
                                <li><span aria-current="page" class="grid size-8 place-items-center rounded-lg bg-brand-50 font-semibold text-brand-700 ring-1 ring-brand-200">{{ $page }}</span></li>
                            @else
                                <li class="hidden sm:block"><a href="{{ $url }}" aria-label="Ir a la página {{ $page }}" class="grid size-8 place-items-center rounded-lg hover:bg-slate-100">{{ $page }}</a></li>
                            @endif
                        @endforeach
                    @endif
                @endforeach

                <li>
                    @if ($paginator->hasMorePages())
                        <a href="{{ $paginator->nextPageUrl() }}" rel="next" class="inline-flex h-8 items-center gap-1 rounded-lg px-2 font-medium text-ink hover:bg-slate-100">Siguiente <x-icon name="chevron-right" /></a>
                    @else
                        <span class="inline-flex h-8 items-center gap-1 px-2 text-slate-300" aria-disabled="true">Siguiente <x-icon name="chevron-right" /></span>
                    @endif
                </li>
            </ul>
        @endif
    </nav>
@endif
