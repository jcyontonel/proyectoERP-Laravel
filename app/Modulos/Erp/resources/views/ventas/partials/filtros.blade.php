@php
    $hayFiltros = request()->hasAny(['q', 'tipo', 'estado', 'desde', 'hasta']);
@endphp

<form method="GET" action="{{ route('ventas.index') }}" class="mt-6 rounded-xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5">
    <div class="flex items-center justify-between">
        <label for="q" class="field-label !mb-0">Buscar venta</label>
        @if ($hayFiltros)
            <a href="{{ route('ventas.index') }}" class="text-xs font-semibold text-brand-600 hover:text-brand-700">Limpiar filtros</a>
        @endif
    </div>
    <div class="relative mt-1.5">
        <x-icon name="search" class="pointer-events-none absolute left-3 top-3 text-slate-400" />
        <input id="q" name="q" type="search" value="{{ request('q') }}" autocomplete="off"
            placeholder="Buscar por N° comprobante, cliente o documento..." class="field pl-9">
    </div>

    <div class="mt-4 grid grid-cols-2 items-end gap-4 lg:grid-cols-[1fr_1fr_1.5fr_auto_auto]">
        <x-select name="tipo" label="Tipo de Comprobante">
            <option value="">Todos</option>
            @foreach (config('ventas.tipos_comprobante') as $valor => $etiqueta)
                <option value="{{ $valor }}" @selected(request('tipo') === $valor)>{{ $etiqueta }}</option>
            @endforeach
        </x-select>

        <x-select name="estado" label="Estado">
            <option value="">Todos</option>
            @foreach (['registrada' => 'Completada', 'borrador' => 'Borrador', 'anulada' => 'Anulada'] as $valor => $etiqueta)
                <option value="{{ $valor }}" @selected(request('estado') === $valor)>{{ $etiqueta }}</option>
            @endforeach
        </x-select>

        <div class="col-span-2 lg:col-span-1">
            <span class="field-label">Rango de Fechas</span>
            <div class="field flex items-center gap-2 px-3 focus-within:border-brand-500 focus-within:ring-2 focus-within:ring-brand-500/20">
                <x-icon name="calendar" class="text-slate-400" />
                <input type="date" name="desde" value="{{ request('desde') }}" aria-label="Desde"
                    class="min-w-0 flex-1 border-0 bg-transparent p-0 text-sm focus:outline-none">
                <span class="text-slate-400" aria-hidden="true">–</span>
                <input type="date" name="hasta" value="{{ request('hasta') }}" aria-label="Hasta"
                    class="min-w-0 flex-1 border-0 bg-transparent p-0 text-sm focus:outline-none">
            </div>
        </div>

        <button type="submit" class="btn btn-secondary w-full">
            <x-icon name="sliders" class="text-brand-600" /> Filtrar
        </button>

        <details data-dropdown class="relative">
            <summary class="btn btn-secondary w-full cursor-pointer list-none whitespace-nowrap [&::-webkit-details-marker]:hidden">
                <x-icon name="download" class="text-brand-600" /> Exportar Excel/PDF
            </summary>
            <div class="absolute right-0 z-20 mt-2 w-48 rounded-xl border border-slate-200 bg-white p-1.5 shadow-lg">
                <a href="{{ route('ventas.export', request()->except('page') + ['formato' => 'xlsx']) }}" class="flex items-center gap-2 rounded-lg px-3 py-2 text-sm hover:bg-slate-50">Excel (.csv)</a>
                <a href="{{ route('ventas.export', request()->except('page') + ['formato' => 'pdf']) }}" class="flex items-center gap-2 rounded-lg px-3 py-2 text-sm hover:bg-slate-50">PDF (imprimir)</a>
            </div>
        </details>
    </div>
</form>
