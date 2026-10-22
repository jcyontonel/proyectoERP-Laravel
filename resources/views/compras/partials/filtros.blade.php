@php $hayFiltros = request()->hasAny(['q', 'proveedor_id', 'estado', 'pago', 'desde', 'hasta']); @endphp

<form method="GET" action="{{ route('compras.index') }}" class="mt-6 rounded-xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5">
    <div class="flex items-center justify-between">
        <label for="q" class="field-label !mb-0">Buscar compra</label>
        @if ($hayFiltros)
            <a href="{{ route('compras.index') }}" class="text-xs font-semibold text-brand-600 hover:text-brand-700">Limpiar filtros</a>
        @endif
    </div>
    <div class="relative mt-1.5">
        <x-icon name="search" class="pointer-events-none absolute left-3 top-3 text-slate-400" />
        <input id="q" name="q" type="search" value="{{ request('q') }}" autocomplete="off"
            placeholder="Buscar por comprobante, proveedor o RUC..." class="field pl-9">
    </div>

    <div class="mt-4 grid grid-cols-2 items-end gap-4 lg:grid-cols-[1.3fr_1fr_1fr_1.5fr_auto_auto]">
        <div class="col-span-2 lg:col-span-1">
            <x-select name="proveedor_id" label="Proveedor">
                <option value="">Todos</option>
                @foreach ($proveedores as $p)
                    <option value="{{ $p->id }}" @selected((string) request('proveedor_id') === (string) $p->id)>{{ $p->razon_social ?: $p->nombre_comercial }}</option>
                @endforeach
            </x-select>
        </div>

        <x-select name="estado" label="Estado">
            <option value="">Todos</option>
            @foreach (['registrada' => 'Registrada', 'anulada' => 'Anulada'] as $valor => $etiqueta)
                <option value="{{ $valor }}" @selected(request('estado') === $valor)>{{ $etiqueta }}</option>
            @endforeach
        </x-select>

        <x-select name="pago" label="Pago">
            <option value="">Todos</option>
            <option value="pendiente" @selected(request('pago') === 'pendiente')>Por pagar</option>
            <option value="pagada" @selected(request('pago') === 'pagada')>Pagadas</option>
        </x-select>

        <div class="col-span-2 lg:col-span-1">
            <span class="field-label">Rango de fechas</span>
            <div class="field flex items-center gap-2 px-3 focus-within:border-brand-500 focus-within:ring-2 focus-within:ring-brand-500/20">
                <x-icon name="calendar" class="text-slate-400" />
                <input type="date" name="desde" value="{{ request('desde') }}" aria-label="Desde" class="min-w-0 flex-1 border-0 bg-transparent p-0 text-sm focus:outline-none">
                <span class="text-slate-400" aria-hidden="true">–</span>
                <input type="date" name="hasta" value="{{ request('hasta') }}" aria-label="Hasta" class="min-w-0 flex-1 border-0 bg-transparent p-0 text-sm focus:outline-none">
            </div>
        </div>

        <button type="submit" class="btn btn-secondary w-full"><x-icon name="sliders" class="text-brand-600" /> Filtrar</button>

        <a href="{{ route('compras.export', request()->except('page')) }}" class="btn btn-secondary w-full whitespace-nowrap">
            <x-icon name="download" class="text-brand-600" /> Exportar CSV
        </a>
    </div>
</form>
