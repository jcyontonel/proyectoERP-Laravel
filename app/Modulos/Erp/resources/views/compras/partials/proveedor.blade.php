<x-card title="Proveedor" icon="building" :class="$class ?? ''">
    <label for="proveedor-buscar" class="field-label">Buscar por RUC o razón social</label>
    <div class="relative">
        <x-icon name="search" class="pointer-events-none absolute left-3 top-3 text-slate-400" />
        <input id="proveedor-buscar" type="text" autocomplete="off" role="combobox" aria-expanded="false"
            aria-controls="proveedor-resultados" aria-autocomplete="list"
            placeholder="RUC o nombre del proveedor" class="field pl-9 pr-16">
        <div class="absolute right-2 top-0 flex h-10 items-center gap-1.5">
            <button type="button" id="proveedor-limpiar" hidden aria-label="Limpiar búsqueda" class="grid size-6 place-items-center rounded text-slate-400 hover:text-ink">
                <x-icon name="x" class="size-3.5" />
            </button>
            <kbd class="text-[11px] text-slate-400">F2</kbd>
        </div>
        <ul id="proveedor-resultados" role="listbox" hidden class="absolute inset-x-0 top-full z-20 mt-1.5 max-h-64 overflow-y-auto rounded-lg border border-slate-200 bg-white p-1 shadow-lg"></ul>
    </div>

    <input type="hidden" name="proveedor_id" id="proveedor_id" value="{{ old('proveedor_id') }}">
    @error('proveedor_id')
        <p class="mt-1.5 text-xs text-red-600">{{ $message }}</p>
    @enderror

    <div id="proveedor-card" hidden class="mt-4 rounded-lg border border-brand-100 bg-brand-50 p-3.5">
        <div class="flex items-center gap-3">
            <span id="proveedor-iniciales" class="grid size-9 shrink-0 place-items-center rounded-full bg-white text-xs font-semibold text-brand-700"></span>
            <div class="min-w-0 flex-1 leading-tight">
                <p id="proveedor-nombre" class="truncate text-sm font-semibold"></p>
                <p id="proveedor-doc" class="mt-0.5 text-xs text-slate-500"></p>
            </div>
            <x-icon name="check-circle" class="size-[18px] text-brand-600" />
        </div>
        <p id="proveedor-contacto" hidden class="mt-3 flex flex-wrap gap-x-5 gap-y-1 text-xs text-slate-600">
            <span id="proveedor-persona"></span>
            <span id="proveedor-telefono"></span>
            <span id="proveedor-email" class="break-all"></span>
        </p>
    </div>

    <div id="proveedor-vacio" class="mt-4 rounded-lg border border-dashed border-slate-300 p-3.5 text-center text-xs text-slate-500">
        Selecciona el proveedor al que le compraste
    </div>

    <div class="mt-4 flex items-center justify-between text-xs font-semibold text-brand-600">
        <button type="button" id="proveedor-cambiar" hidden class="hover:text-brand-700">Cambiar proveedor</button>
        <button type="button" id="proveedor-nuevo" class="ml-auto hover:text-brand-700">+ Nuevo proveedor</button>
    </div>
</x-card>
