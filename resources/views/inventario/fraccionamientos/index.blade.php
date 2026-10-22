{{-- FraccionamientoController@index: $fraccionamientos, $almacenes, $reglas (cantidad de reglas activas) --}}
@extends('layouts.app')

@use('App\Support\Unidades')

@section('title', 'Fraccionamientos')

@section('content')
    <x-page-header title="Fraccionamientos" :migas="['Inventario' => null, 'Fraccionamientos' => null]"
        subtitle="Partir un producto en otro: abrir un saco de 15 kg para vender por kilo. Sale del origen, entra al destino y el costo se reparte.">
        <x-slot:acciones>
            <a href="{{ route('fraccionamientos.create') }}" class="btn btn-primary"><x-icon name="plus" /> Partir producto</a>
        </x-slot:acciones>
    </x-page-header>

    <x-flash />

    @if (! $reglas)
        <x-alert tone="info" class="mt-4">
            Para partir productos primero define la regla en la ficha del producto de origen (ej. el saco): «1 saco = 15 kg de "… por kilo"».
            <a href="{{ route('productos.index') }}" class="font-semibold underline">Ir a productos</a>
        </x-alert>
    @endif

    <form method="GET" class="mt-6 grid items-end gap-4 rounded-xl border border-slate-200 bg-white p-4 shadow-sm sm:grid-cols-3 sm:p-5 lg:grid-cols-[1.4fr_1fr_1fr_1fr_1fr_auto]">
        <x-input name="q" label="Producto" :value="request('q')" placeholder="Origen o destino…" />
        <x-select name="tipo" label="Origen">
            <option value="">Todos</option>
            <option value="manual" @selected(request('tipo') === 'manual')>Manual</option>
            <option value="venta" @selected(request('tipo') === 'venta')>Automático al vender</option>
        </x-select>
        <x-select name="almacen_id" label="Almacén">
            <option value="">Todos</option>
            @foreach ($almacenes as $a)<option value="{{ $a->id }}" @selected(request('almacen_id') == $a->id)>{{ $a->nombre }}</option>@endforeach
        </x-select>
        <x-input name="desde" label="Desde" type="date" :value="request('desde')" />
        <x-input name="hasta" label="Hasta" type="date" :value="request('hasta')" />
        <button type="submit" class="btn btn-secondary"><x-icon name="sliders" class="text-brand-600" /> Filtrar</button>
    </form>

    <section class="mt-6 overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
        @if ($fraccionamientos->isEmpty())
            <x-empty-state icon="layers" title="Sin fraccionamientos" description="Aquí verás cada saco abierto, ya sea manual o automático al vender por kilo." />
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="border-b border-slate-200 bg-slate-50/70 text-left text-xs font-medium text-slate-500">
                        <tr>
                            <th class="py-3 pl-5 pr-3">Documento</th>
                            <th class="px-3 py-3">Se partió</th>
                            <th class="px-3 py-3">Se obtuvo</th>
                            <th class="px-3 py-3">Origen</th>
                            <th class="py-3 pl-3 pr-5">Estado</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($fraccionamientos as $f)
                            <tr @class(['hover:bg-slate-50/60', 'opacity-60' => $f->esAnulado()])>
                                <td class="py-3 pl-5 pr-3">
                                    <a href="{{ route('fraccionamientos.show', $f) }}" class="font-semibold text-brand-600 hover:text-brand-700">{{ $f->numero_completo }}</a>
                                    <p class="text-xs text-slate-500">{{ $f->fecha_movimiento->format('d/m/Y H:i') }} · {{ $f->almacen?->nombre }}</p>
                                </td>
                                <td class="px-3 py-3">
                                    <span class="font-semibold text-red-600">−{{ Unidades::conUnidad($f->cantidad_origen, $f->productoOrigen?->tipoUnidad?->descripcion) }}</span>
                                    <p class="text-xs text-slate-500">{{ $f->productoOrigen?->nombre }}</p>
                                </td>
                                <td class="px-3 py-3">
                                    <span class="font-semibold text-emerald-700">+{{ Unidades::conUnidad($f->cantidad_destino, $f->productoDestino?->tipoUnidad?->descripcion) }}</span>
                                    <p class="text-xs text-slate-500">{{ $f->productoDestino?->nombre }}</p>
                                </td>
                                <td class="px-3 py-3">
                                    @if ($f->origen === 'venta')
                                        <x-badge tone="info" shape="box">Al vender</x-badge>
                                        @if ($f->venta)<a href="{{ route('ventas.show', $f->venta_id) }}" class="block text-xs text-brand-600 hover:text-brand-700">{{ $f->venta->numero_completo }}</a>@endif
                                    @else
                                        <span class="text-xs text-slate-600">Manual · {{ $f->usuario?->nombre_completo }}</span>
                                    @endif
                                </td>
                                <td class="py-3 pl-3 pr-5"><x-estado-inventario :estado="$f->esAnulado() ? 'anulado' : 'confirmado'" /></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="border-t border-slate-200 px-4 py-3 sm:px-5">{{ $fraccionamientos->links() }}</div>
        @endif
    </section>
@endsection
