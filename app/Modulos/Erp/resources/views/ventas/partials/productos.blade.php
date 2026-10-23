<x-card title="Productos" icon="package" :class="$class ?? ''">
    <x-slot:aside><span id="productos-contador">Sin productos</span></x-slot:aside>

    {{-- Buscador --}}
    <label for="producto-buscar" class="field-label">Buscar por nombre o código</label>
    <div class="relative">
        <x-icon name="search" class="pointer-events-none absolute left-3 top-3 text-slate-400" />
        <input id="producto-buscar" type="text" autocomplete="off" role="combobox" aria-expanded="false"
            aria-controls="producto-resultados" aria-autocomplete="list"
            placeholder="Nombre o código del producto" class="field pl-9 pr-16">
        <div class="absolute right-2 top-0 flex h-10 items-center gap-1.5">
            <button type="button" id="producto-limpiar" hidden aria-label="Limpiar búsqueda" class="grid size-6 place-items-center rounded text-slate-400 hover:text-ink">
                <x-icon name="x" class="size-3.5" />
            </button>
            <kbd class="text-[11px] text-slate-400">F3</kbd>
        </div>
        <ul id="producto-resultados" role="listbox" hidden class="absolute inset-x-0 top-full z-20 mt-1.5 max-h-72 overflow-y-auto rounded-lg border border-slate-200 bg-white p-1 shadow-lg"></ul>
    </div>

    {{-- Producto elegido: cantidad + Agregar --}}
    <div id="producto-seleccion" hidden class="mt-3 flex flex-wrap items-center gap-x-4 gap-y-3 rounded-lg border border-slate-200 bg-slate-50 p-3">
        <div class="flex min-w-0 basis-full items-center gap-3 sm:flex-1 sm:basis-auto">
            <span class="grid size-8 shrink-0 place-items-center rounded-md bg-white text-slate-500 ring-1 ring-slate-200">
                <x-icon name="package" />
            </span>
            <div class="min-w-0 leading-tight">
                <p id="sel-nombre" class="truncate text-sm font-semibold"></p>
                <p id="sel-detalle" class="mt-0.5 text-xs text-slate-500"></p>
            </div>
        </div>
        <div class="flex flex-1 items-center justify-between gap-3 sm:flex-none sm:justify-end">
            <span id="sel-precio" class="text-sm font-semibold"></span>
            <x-qty id="producto-cantidad" />
            <button type="button" id="producto-agregar" class="btn btn-primary"><x-icon name="plus" /> Agregar</button>
        </div>
    </div>
    <p id="producto-aviso" hidden role="alert" class="mt-2 text-xs font-medium text-red-600"></p>

    {{-- Cabecera de la tabla (≥ sm) --}}
    <div class="mt-5 hidden grid-cols-[minmax(0,1fr)_5.5rem_8rem_6rem_2rem] items-center gap-x-3 border-b border-slate-200 pb-2 text-[11px] font-medium uppercase tracking-wide text-slate-500 sm:grid">
        <span>Producto</span>
        <span class="text-right">Precio unit.</span>
        <span class="text-center">Cantidad</span>
        <span class="text-right">Importe</span>
        <span></span>
    </div>

    {{-- Líneas (las dibuja el JS) --}}
    <ul id="items-lista" class="mt-2 divide-y divide-slate-100 sm:mt-0">
        <li id="items-vacio" class="py-10 text-center text-sm text-slate-500">
            Aún no agregaste productos. Busca uno arriba para empezar.
        </li>
    </ul>

    <template id="tpl-item">
        <li data-item class="grid grid-cols-[minmax(0,1fr)_auto] items-center gap-x-3 gap-y-2.5 py-3 sm:grid-cols-[minmax(0,1fr)_5.5rem_8rem_6rem_2rem]">
            <div class="col-start-1 flex min-w-0 items-center gap-3">
                <span class="grid size-8 shrink-0 place-items-center rounded-md bg-slate-100 text-slate-500">
                    <x-icon name="package" />
                </span>
                <div class="min-w-0 leading-tight">
                    <p data-nombre class="truncate text-sm font-semibold"></p>
                    <p data-codigo class="mt-0.5 text-xs text-slate-500"></p>
                </div>
            </div>

            <button type="button" data-remove class="col-start-2 row-start-1 grid size-8 place-items-center rounded-md text-slate-400 hover:bg-red-50 hover:text-red-600 sm:col-start-5">
                <x-icon name="trash" />
            </button>

            <div class="col-span-2 flex items-center justify-between gap-3 sm:contents">
                <span data-precio class="text-sm text-slate-600 sm:text-right"></span>
                <span class="sm:flex sm:justify-center"><x-qty /></span>
                <span data-importe class="text-right text-sm font-semibold"></span>
            </div>
        </li>
    </template>

    <div class="mt-1 flex items-center justify-between border-t border-slate-200 pt-3 text-xs text-slate-500">
        <span id="unidades-total">0 unidades en total</span>
        <span>{{ ($preciosIncluyenIgv ?? false) ? 'Precios con IGV' : 'Precios sin IGV' }}</span>
    </div>
</x-card>
