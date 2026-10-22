<form method="GET" action="{{ route('proveedores.index') }}" class="mt-6 rounded-xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5">
    <div class="grid items-end gap-4 sm:grid-cols-[1fr_12rem_auto]">
        <div>
            <div class="flex items-center justify-between">
                <label for="q" class="field-label">Buscar proveedor</label>
                @if (request()->hasAny(['q', 'estado']))
                    <a href="{{ route('proveedores.index') }}" class="mb-1.5 text-xs font-semibold text-brand-600 hover:text-brand-700">Limpiar</a>
                @endif
            </div>
            <div class="relative">
                <x-icon name="search" class="pointer-events-none absolute left-3 top-3 text-slate-400" />
                <input id="q" name="q" type="search" value="{{ request('q') }}" autocomplete="off"
                    placeholder="Razón social, RUC o contacto..." class="field pl-9">
            </div>
        </div>

        <x-select name="estado" label="Estado">
            <option value="">Todos</option>
            <option value="activos" @selected(request('estado') === 'activos')>Activos</option>
            <option value="inactivos" @selected(request('estado') === 'inactivos')>Inactivos</option>
        </x-select>

        <button type="submit" class="btn btn-secondary w-full sm:w-auto"><x-icon name="sliders" class="text-brand-600" /> Filtrar</button>
    </div>
</form>
