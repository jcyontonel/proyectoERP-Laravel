{{-- CotizacionController@index: $cotizaciones (paginador), $abiertas y $convertidas (cantidad, total). Filtros: q, estado, desde, hasta --}}
@extends('layouts.app')

@section('title', 'Cotizaciones')

@php $hayFiltros = request()->hasAny(['q', 'estado', 'desde', 'hasta']); @endphp

@section('content')
    <x-page-header title="Cotizaciones" :migas="['Ventas' => route('ventas.index'), 'Cotizaciones' => null]"
        subtitle="Proformas para tus clientes. No mueven stock ni usan comprobantes; al aceptarse, conviértelas en venta.">
        <x-slot:acciones>
            <a href="{{ route('cotizaciones.create') }}" class="btn btn-primary"><x-icon name="plus-circle" /> Nueva cotización</a>
        </x-slot:acciones>
    </x-page-header>

    <x-flash />

    <div class="mt-6 grid gap-4 sm:grid-cols-2">
        <x-stat-card title="Abiertas (vigentes)" icon="clipboard" :value="'S/ ' . number_format((float) $abiertas->total, 2)">{{ (int) $abiertas->cantidad }} por cerrar con el cliente</x-stat-card>
        <x-stat-card title="Convertidas este mes" icon="receipt" tone="emerald" :value="'S/ ' . number_format((float) $convertidas->total, 2)">{{ (int) $convertidas->cantidad }} ya son venta</x-stat-card>
    </div>

    <form method="GET" class="mt-6 grid items-end gap-4 rounded-xl border border-slate-200 bg-white p-4 shadow-sm sm:grid-cols-2 sm:p-5 lg:grid-cols-[2fr_1fr_1fr_1fr_auto]">
        <div class="sm:col-span-2 lg:col-span-1">
            <div class="flex items-center justify-between">
                <label for="q" class="field-label !mb-0">Buscar</label>
                @if ($hayFiltros)<a href="{{ route('cotizaciones.index') }}" class="text-xs font-semibold text-brand-600">Limpiar</a>@endif
            </div>
            <input id="q" name="q" type="search" value="{{ request('q') }}" placeholder="N.º de cotización o cliente..." class="field mt-1.5">
        </div>
        <x-select name="estado" label="Estado">
            <option value="">Todos</option>
            @foreach (\App\Modulos\Erp\Models\Cotizacion::ESTADOS as $valor => [$etiqueta])
                <option value="{{ $valor }}" @selected(request('estado') === $valor)>{{ $etiqueta }}</option>
            @endforeach
        </x-select>
        <x-input name="desde" label="Desde" type="date" :value="request('desde')" />
        <x-input name="hasta" label="Hasta" type="date" :value="request('hasta')" />
        <button type="submit" class="btn btn-secondary"><x-icon name="sliders" class="text-brand-600" /> Filtrar</button>
    </form>

    <section class="mt-6 overflow-x-auto rounded-xl border border-slate-200 bg-white shadow-sm">
        @if ($cotizaciones->isEmpty())
            <x-empty-state icon="clipboard" title="Sin cotizaciones" description="Crea una cotización para enviarla a tu cliente; cuando la acepte, conviértela en venta con un clic." />
        @else
            <table class="w-full text-sm">
                <thead class="border-b border-slate-200 bg-slate-50/70 text-left text-xs font-medium text-slate-500">
                    <tr><th class="py-3 pl-5 pr-3">Cotización</th><th class="px-3 py-3">Cliente</th><th class="px-3 py-3">Emisión</th><th class="px-3 py-3">Válida hasta</th><th class="px-3 py-3 text-right">Total</th><th class="py-3 pl-3 pr-5">Estado</th></tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach ($cotizaciones as $c)
                        @php [$etiqueta, $tono] = \App\Modulos\Erp\Models\Cotizacion::ESTADOS[$c->estadoEfectivo()]; @endphp
                        <tr class="hover:bg-slate-50/60">
                            <td class="py-3 pl-5 pr-3"><a href="{{ route('cotizaciones.show', $c) }}" class="font-semibold text-brand-600 hover:text-brand-700">{{ $c->numero_completo }}</a></td>
                            <td class="px-3 py-3">{{ $c->cliente?->nombre_completo ?? 'Sin cliente' }}</td>
                            <td class="whitespace-nowrap px-3 py-3">{{ $c->fecha_emision->format('d/m/Y') }}</td>
                            <td class="whitespace-nowrap px-3 py-3">{{ $c->vence_el->format('d/m/Y') }}</td>
                            <td @class(['px-3 py-3 text-right font-semibold', 'line-through text-slate-400' => $c->estado === 'anulada'])><x-money :value="$c->total" /></td>
                            <td class="py-3 pl-3 pr-5">
                                <x-badge :tone="$tono" dot>{{ $etiqueta }}</x-badge>
                                @if ($c->venta)<a href="{{ route('ventas.show', $c->venta) }}" class="ml-1 text-xs text-brand-600 hover:underline">{{ $c->venta->numero_completo }}</a>@endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            @if ($cotizaciones->hasPages())
                <div class="border-t border-slate-200 px-4 py-3 sm:px-5">{{ $cotizaciones->links('vendor.pagination.ricopet') }}</div>
            @endif
        @endif
    </section>
@endsection
