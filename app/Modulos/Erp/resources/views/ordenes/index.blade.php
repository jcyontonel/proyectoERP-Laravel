{{-- OrdenCompraController@index: $ordenes (paginador), $pendientes (cantidad, total), $atrasadas, $proveedores. Filtros: q, estado, proveedor_id, desde, hasta --}}
@extends('layouts.app')

@section('title', 'Órdenes de compra')

@php $hayFiltros = request()->hasAny(['q', 'estado', 'proveedor_id', 'desde', 'hasta']); @endphp

@section('content')
    <x-page-header title="Órdenes de compra" :migas="['Compras' => route('compras.index'), 'Órdenes de compra' => null]"
        subtitle="Lo que pediste a tus proveedores. Al llegar la mercadería, regístrala como compra desde la orden.">
        <x-slot:acciones>
            <a href="{{ route('ordenes.create') }}" class="btn btn-primary"><x-icon name="plus-circle" /> Nueva orden</a>
        </x-slot:acciones>
    </x-page-header>

    <x-flash />

    <div class="mt-6 grid gap-4 sm:grid-cols-2">
        <x-stat-card title="Por recibir" icon="truck" :value="'S/ ' . number_format((float) $pendientes->total, 2)">{{ (int) $pendientes->cantidad }} órdenes emitidas o recibidas en parte</x-stat-card>
        <x-stat-card title="Atrasadas" icon="calendar-clock" :tone="$atrasadas ? 'amber' : 'slate'" :value="$atrasadas">pasó la fecha de entrega</x-stat-card>
    </div>

    <form method="GET" class="mt-6 grid items-end gap-4 rounded-xl border border-slate-200 bg-white p-4 shadow-sm sm:grid-cols-2 sm:p-5 lg:grid-cols-[2fr_1fr_1.5fr_1fr_1fr_auto]">
        <div class="sm:col-span-2 lg:col-span-1">
            <div class="flex items-center justify-between">
                <label for="q" class="field-label !mb-0">Buscar</label>
                @if ($hayFiltros)<a href="{{ route('ordenes.index') }}" class="text-xs font-semibold text-brand-600">Limpiar</a>@endif
            </div>
            <input id="q" name="q" type="search" value="{{ request('q') }}" placeholder="N.º de orden o proveedor..." class="field mt-1.5">
        </div>
        <x-select name="estado" label="Estado">
            <option value="">Todos</option>
            @foreach (\App\Modulos\Erp\Models\OrdenCompra::ESTADOS as $valor => [$etiqueta])
                <option value="{{ $valor }}" @selected(request('estado') === $valor)>{{ $etiqueta }}</option>
            @endforeach
        </x-select>
        <x-select name="proveedor_id" label="Proveedor">
            <option value="">Todos</option>
            @foreach ($proveedores as $p)
                <option value="{{ $p->id }}" @selected((string) request('proveedor_id') === (string) $p->id)>{{ $p->nombre }}</option>
            @endforeach
        </x-select>
        <x-input name="desde" label="Desde" type="date" :value="request('desde')" />
        <x-input name="hasta" label="Hasta" type="date" :value="request('hasta')" />
        <button type="submit" class="btn btn-secondary"><x-icon name="sliders" class="text-brand-600" /> Filtrar</button>
    </form>

    <section class="mt-6 overflow-x-auto rounded-xl border border-slate-200 bg-white shadow-sm">
        @if ($ordenes->isEmpty())
            <x-empty-state icon="clipboard" title="Sin órdenes de compra" description="Crea una orden para pedir mercadería; cuando llegue, regístrala como compra y el stock se actualizará." />
        @else
            <table class="w-full text-sm">
                <thead class="border-b border-slate-200 bg-slate-50/70 text-left text-xs font-medium text-slate-500">
                    <tr><th class="py-3 pl-5 pr-3">Orden</th><th class="px-3 py-3">Proveedor</th><th class="px-3 py-3">Almacén</th><th class="px-3 py-3">Emisión</th><th class="px-3 py-3">Entrega</th><th class="px-3 py-3 text-right">Total</th><th class="py-3 pl-3 pr-5">Estado</th></tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach ($ordenes as $o)
                        @php [$etiqueta, $tono] = \App\Modulos\Erp\Models\OrdenCompra::ESTADOS[$o->estado]; @endphp
                        <tr class="hover:bg-slate-50/60">
                            <td class="py-3 pl-5 pr-3"><a href="{{ route('ordenes.show', $o) }}" class="font-semibold text-brand-600 hover:text-brand-700">{{ $o->numero_completo }}</a></td>
                            <td class="px-3 py-3">{{ $o->proveedor?->nombre }}</td>
                            <td class="px-3 py-3">{{ $o->almacen?->nombre }}</td>
                            <td class="whitespace-nowrap px-3 py-3">{{ $o->fecha_emision->format('d/m/Y') }}</td>
                            <td @class(['whitespace-nowrap px-3 py-3', 'font-semibold text-amber-700' => $o->estaAtrasada()])>{{ $o->fecha_entrega?->format('d/m/Y') ?? '—' }}</td>
                            <td @class(['px-3 py-3 text-right font-semibold', 'line-through text-slate-400' => $o->estado === 'anulada'])><x-money :value="$o->total" /></td>
                            <td class="py-3 pl-3 pr-5"><x-badge :tone="$tono" dot>{{ $etiqueta }}</x-badge></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            @if ($ordenes->hasPages())
                <div class="border-t border-slate-200 px-4 py-3 sm:px-5">{{ $ordenes->links('vendor.pagination.ricopet') }}</div>
            @endif
        @endif
    </section>
@endsection
