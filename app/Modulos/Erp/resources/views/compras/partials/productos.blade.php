<x-card title="Productos comprados" icon="package" :class="$class ?? ''">
    <x-slot:aside><span id="productos-contador">Sin productos</span></x-slot:aside>

    {{-- Buscador --}}
    <label for="producto-buscar" class="field-label">Buscar por nombre o código</label>
    <div class="relative">
        <x-icon name="search" class="pointer-events-none absolute left-3 top-3 text-slate-400" />
        <input id="producto-buscar" type="text" autocomplete="off" role="combobox" aria-expanded="false"
            aria-controls="producto-resultados" aria-autocomplete="list"
            placeholder="Nombre o código del producto (o escanea el código de barras)" class="field pl-9 pr-16">
        <div class="absolute right-2 top-0 flex h-10 items-center gap-1.5">
            <button type="button" id="producto-limpiar" hidden aria-label="Limpiar búsqueda" class="grid size-6 place-items-center rounded text-slate-400 hover:text-ink">
                <x-icon name="x" class="size-3.5" />
            </button>
            <kbd class="text-[11px] text-slate-400">F3</kbd>
        </div>
        <ul id="producto-resultados" role="listbox" hidden class="absolute inset-x-0 top-full z-20 mt-1.5 max-h-80 overflow-y-auto rounded-lg border border-slate-200 bg-white p-1 shadow-lg"></ul>
    </div>

    {{-- Producto elegido: información para decidir + costo, cantidad y precio de venta --}}
    <div id="producto-seleccion" hidden class="mt-3 rounded-lg border border-brand-100 bg-brand-50/40 p-3.5">
        <div class="flex items-start gap-3">
            <span class="grid size-9 shrink-0 place-items-center rounded-md bg-white text-brand-600 ring-1 ring-brand-100">
                <x-icon name="package" />
            </span>
            <div class="min-w-0 flex-1 leading-tight">
                <p id="sel-nombre" class="truncate text-sm font-semibold"></p>
                <p id="sel-detalle" class="mt-0.5 text-xs text-slate-500"></p>
            </div>
            <button type="button" id="producto-cerrar" aria-label="Cancelar selección" class="grid size-7 place-items-center rounded text-slate-400 hover:bg-white hover:text-ink"><x-icon name="x" class="size-3.5" /></button>
        </div>

        {{-- Datos de referencia --}}
        <dl class="mt-3 grid grid-cols-2 gap-2 text-xs sm:grid-cols-4">
            <div class="rounded-md bg-white px-2.5 py-2 ring-1 ring-slate-200">
                <dt class="text-slate-500">Stock en almacén</dt>
                <dd id="sel-stock" class="mt-0.5 font-semibold text-ink"></dd>
            </div>
            <div class="rounded-md bg-white px-2.5 py-2 ring-1 ring-slate-200">
                <dt class="text-slate-500">Costo actual (sin IGV)</dt>
                <dd id="sel-costo-actual" class="mt-0.5 font-semibold text-ink"></dd>
            </div>
            <div class="rounded-md bg-white px-2.5 py-2 ring-1 ring-slate-200">
                <dt class="text-slate-500">Precio de venta</dt>
                <dd id="sel-precio-actual" class="mt-0.5 font-semibold text-ink"></dd>
            </div>
            <div class="rounded-md bg-white px-2.5 py-2 ring-1 ring-slate-200">
                <dt class="text-slate-500">Margen actual</dt>
                <dd id="sel-margen-actual" class="mt-0.5 font-semibold text-ink"></dd>
            </div>
        </dl>
        <p id="sel-ultima" class="mt-2 text-[11px] text-slate-500"></p>

        <div class="mt-3 flex flex-wrap items-end gap-3">
            <label class="block">
                <span class="field-label">Costo unitario S/ <span id="sel-costo-nota" class="font-normal text-slate-400"></span></span>
                <input id="sel-costo" type="number" step="0.01" min="0" inputmode="decimal" class="field h-9 w-32 text-right">
            </label>
            <div>
                <span class="field-label">Cantidad</span>
                <x-qty id="producto-cantidad" min="0.01" step="any" />
            </div>
            <label class="block">
                <span class="field-label">Precio de venta S/</span>
                <input id="sel-precio" type="number" step="0.01" min="0" inputmode="decimal" class="field h-9 w-32 text-right">
            </label>
            <button type="button" id="producto-agregar" class="btn btn-primary ml-auto h-9"><x-icon name="plus" /> Agregar</button>
        </div>
        <p id="sel-analisis" class="mt-2 flex flex-wrap items-center gap-x-3 gap-y-1 text-xs"></p>
    </div>
    <p id="producto-aviso" hidden role="alert" class="mt-2 text-xs font-medium text-red-600"></p>

    {{-- Cabecera de la tabla (≥ sm) --}}
    <div class="mt-5 hidden grid-cols-[minmax(0,1fr)_7rem_8rem_6rem_2rem] items-center gap-x-3 border-b border-slate-200 pb-2 text-[11px] font-medium uppercase tracking-wide text-slate-500 sm:grid">
        <span>Producto</span>
        <span class="text-right">Costo unit.</span>
        <span class="text-center">Cantidad</span>
        <span class="text-right">Importe</span>
        <span></span>
    </div>

    <ul id="items-lista" class="mt-2 divide-y divide-slate-100 sm:mt-0">
        <li id="items-vacio" class="py-10 text-center text-sm text-slate-500">
            Aún no agregaste productos. Busca uno arriba para empezar.
        </li>
    </ul>

    <template id="tpl-item">
        <li data-item class="py-3">
            <div class="grid grid-cols-[minmax(0,1fr)_auto] items-center gap-x-3 gap-y-2.5 sm:grid-cols-[minmax(0,1fr)_7rem_8rem_6rem_2rem]">
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
                    <input data-costo type="number" step="0.01" min="0" inputmode="decimal" aria-label="Costo unitario"
                        class="field h-8 w-28 text-right text-sm sm:w-full">
                    <span class="sm:flex sm:justify-center"><x-qty min="0.01" step="any" /></span>
                    <span data-importe class="text-right text-sm font-semibold"></span>
                </div>
            </div>

            {{-- Variación de costo y precio de venta --}}
            <div class="mt-2 flex flex-wrap items-center gap-x-4 gap-y-2 text-xs sm:pl-11">
                <span data-variacion class="text-slate-500"></span>
                <label data-precio-fila class="flex items-center gap-1.5 text-slate-500">
                    Precio de venta S/
                    <input data-precio-venta type="number" step="0.01" min="0" inputmode="decimal" aria-label="Precio de venta"
                        class="field h-7 w-24 text-right text-xs">
                </label>
                <span data-margen></span>
                <button type="button" data-sugerir class="font-semibold text-brand-600 hover:text-brand-700" title="Calcula el precio que conserva el margen que tenía el producto">Mantener margen</button>
                <span data-precio-cambio hidden class="rounded bg-amber-50 px-1.5 py-0.5 font-medium text-amber-700">Se actualizará el precio</span>
            </div>

            {{-- Lote (obligatorio en productos que controlan lotes) --}}
            <div data-lote-fila class="mt-2.5 grid grid-cols-2 gap-3 sm:max-w-md sm:pl-11">
                <input data-lote type="text" maxlength="100" placeholder="Lote" aria-label="Código de lote" class="field h-8 text-xs">
                <input data-vence type="date" aria-label="Fecha de vencimiento" class="field h-8 text-xs">
            </div>
        </li>
    </template>

    <div class="mt-1 flex flex-wrap items-center justify-between gap-2 border-t border-slate-200 pt-3 text-xs text-slate-500">
        <span id="unidades-total">0 unidades en total</span>
        <span id="cambios-precio"></span>
        <span>El costo se registra sin IGV en el kardex</span>
    </div>
</x-card>
