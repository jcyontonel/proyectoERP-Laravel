<x-card title="Cliente" icon="user" :class="$class ?? ''">
    <label for="cliente-buscar" class="field-label">Buscar por documento o nombre</label>
    <div class="relative">
        <x-icon name="search" class="pointer-events-none absolute left-3 top-3 text-slate-400" />
        <input id="cliente-buscar" type="text" autocomplete="off" role="combobox" aria-expanded="false"
            aria-controls="cliente-resultados" aria-autocomplete="list"
            placeholder="DNI, RUC o nombre del cliente" class="field pl-9 pr-16">
        <div class="absolute right-2 top-0 flex h-10 items-center gap-1.5">
            <button type="button" id="cliente-limpiar" hidden aria-label="Limpiar búsqueda" class="grid size-6 place-items-center rounded text-slate-400 hover:text-ink">
                <x-icon name="x" class="size-3.5" />
            </button>
            <kbd class="text-[11px] text-slate-400">F2</kbd>
        </div>
        <ul id="cliente-resultados" role="listbox" hidden class="absolute inset-x-0 top-full z-20 mt-1.5 max-h-64 overflow-y-auto rounded-lg border border-slate-200 bg-white p-1 shadow-lg"></ul>
    </div>
    <p class="mt-1.5 text-[11px] text-slate-500">¿No está en la lista? Escribe el DNI u RUC completo: se busca en RENIEC/SUNAT y se registra solo.</p>
    <p id="cliente-consulta" hidden role="status" aria-live="polite" class="mt-2 flex items-center gap-2 rounded-lg px-3 py-2 text-xs"></p>

    <input type="hidden" name="cliente_id" id="cliente_id" value="{{ old('cliente_id', $venta?->cliente_id) }}">

    {{-- Cliente seleccionado --}}
    <div id="cliente-card" hidden class="mt-4 rounded-lg border border-brand-100 bg-brand-50 p-3.5">
        <div class="flex items-center gap-3">
            <span id="cliente-iniciales" class="grid size-9 shrink-0 place-items-center rounded-full bg-white text-xs font-semibold text-brand-700"></span>
            <div class="min-w-0 flex-1 leading-tight">
                <p id="cliente-nombre" class="truncate text-sm font-semibold"></p>
                <p id="cliente-doc" class="mt-0.5 text-xs text-slate-500"></p>
            </div>
            <x-icon name="check-circle" class="size-[18px] text-brand-600" />
        </div>
        <p id="cliente-contacto" hidden class="mt-3 flex flex-wrap gap-x-5 gap-y-1 text-xs text-slate-600">
            <span id="cliente-email" class="break-all"></span>
            <span id="cliente-telefono"></span>
        </p>
    </div>

    {{-- Sin cliente --}}
    <div id="cliente-vacio" class="mt-4 rounded-lg border border-dashed border-slate-300 p-3.5 text-center text-xs text-slate-500">
        Sin cliente seleccionado · se registrará como venta al público
    </div>

    <div class="mt-4 flex items-center justify-between text-xs font-semibold text-brand-600">
        <button type="button" id="cliente-cambiar" hidden class="hover:text-brand-700">Cambiar cliente</button>
        <a href="{{ route('clientes.create') }}" target="_blank" rel="noopener" class="ml-auto hover:text-brand-700">+ Nuevo cliente</a>
    </div>
</x-card>
