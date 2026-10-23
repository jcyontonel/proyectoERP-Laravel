@php $hayFiltros = request()->hasAny(['q', 'tipo_documento_id', 'estado']); @endphp

<form method="GET" action="{{ route('clientes.index') }}" class="mt-6 rounded-xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5">
    <div class="grid items-end gap-4 md:grid-cols-[minmax(0,2fr)_1fr_1fr_auto]">
        <div>
            <div class="flex items-center justify-between">
                <label for="q" class="field-label">Buscar cliente</label>
                @if ($hayFiltros)
                    <a href="{{ route('clientes.index') }}" class="mb-1.5 text-xs font-semibold text-brand-600 hover:text-brand-700">Limpiar filtros</a>
                @endif
            </div>
            <div class="relative">
                <x-icon name="search" class="pointer-events-none absolute left-3 top-3 text-slate-400" />
                <input id="q" name="q" type="search" value="{{ request('q') }}" autocomplete="off"
                    placeholder="Nombre, documento, correo o teléfono..." class="field pl-9">
            </div>
        </div>

        <x-select name="tipo_documento_id" label="Documento">
            <option value="">Todos</option>
            @foreach ($tiposDocumento as $t)
                <option value="{{ $t->id }}" @selected((string) request('tipo_documento_id') === (string) $t->id)>{{ $t->abreviatura }} · {{ $t->descripcion }}</option>
            @endforeach
        </x-select>

        <x-select name="estado" label="Estado">
            <option value="">Todos</option>
            <option value="activos" @selected(request('estado') === 'activos')>Activos</option>
            <option value="inactivos" @selected(request('estado') === 'inactivos')>Inactivos</option>
        </x-select>

        <button type="submit" class="btn btn-secondary w-full"><x-icon name="sliders" class="text-brand-600" /> Filtrar</button>
    </div>
</form>
