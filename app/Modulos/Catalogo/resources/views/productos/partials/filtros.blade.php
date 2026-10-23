@php $hayFiltros = request()->hasAny(['q', 'categoria_id', 'tipo', 'estado', 'stock', 'orden']); @endphp

<form method="GET" action="{{ route('productos.index') }}" class="mt-6 rounded-xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5">
    <div class="flex items-center justify-between">
        <label for="q" class="field-label !mb-0">Buscar producto</label>
        @if ($hayFiltros)
            <a href="{{ route('productos.index') }}" class="text-xs font-semibold text-brand-600 hover:text-brand-700">Limpiar filtros</a>
        @endif
    </div>
    <div class="relative mt-1.5">
        <x-icon name="search" class="pointer-events-none absolute left-3 top-3 text-slate-400" />
        <input id="q" name="q" type="search" value="{{ request('q') }}" autocomplete="off"
            placeholder="Nombre, código o descripción..." class="field pl-9">
    </div>

    <div class="mt-4 grid grid-cols-2 items-end gap-4 md:grid-cols-3 xl:grid-cols-[1.3fr_1fr_1fr_1fr_1fr_auto]">
        <div class="col-span-2 md:col-span-1">
            <x-select name="categoria_id" label="Categoría">
                <option value="">Todas</option>
                @foreach ($categorias as $c)
                    <option value="{{ $c->id }}" @selected((string) request('categoria_id') === (string) $c->id)>{{ $c->nombre }}</option>
                @endforeach
            </x-select>
        </div>

        <x-select name="tipo" label="Tipo">
            <option value="">Todos</option>
            <option value="producto" @selected(request('tipo') === 'producto')>Productos</option>
            <option value="servicio" @selected(request('tipo') === 'servicio')>Servicios</option>
        </x-select>

        <x-select name="estado" label="Estado">
            <option value="">Todos</option>
            <option value="activos" @selected(request('estado') === 'activos')>Activos</option>
            <option value="inactivos" @selected(request('estado') === 'inactivos')>Inactivos</option>
        </x-select>

        <x-select name="stock" label="Stock">
            <option value="">Todo</option>
            <option value="bajo" @selected(request('stock') === 'bajo')>Stock bajo</option>
            <option value="agotado" @selected(request('stock') === 'agotado')>Agotado</option>
        </x-select>

        <x-select name="orden" label="Ordenar por">
            <option value="nombre" @selected(request('orden', 'nombre') === 'nombre')>Nombre</option>
            <option value="precio" @selected(request('orden') === 'precio')>Mayor precio</option>
            <option value="stock" @selected(request('orden') === 'stock')>Menor stock</option>
            <option value="recientes" @selected(request('orden') === 'recientes')>Más recientes</option>
        </x-select>

        <button type="submit" class="btn btn-secondary col-span-2 w-full md:col-span-1"><x-icon name="sliders" class="text-brand-600" /> Filtrar</button>
    </div>
</form>
