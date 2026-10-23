{{-- NotaController@index: $notas (paginador), $totales [tipo => cantidad, total]. Filtros: q, tipo, estado, desde, hasta --}}
@extends('layouts.app')

@section('title', 'Notas de crédito y débito')

@php $hayFiltros = request()->hasAny(['q', 'tipo', 'estado', 'desde', 'hasta']); @endphp

@section('content')
    <x-page-header title="Notas de crédito y débito" :migas="['Ventas' => route('ventas.index'), 'Notas' => null]"
        subtitle="Se emiten desde el detalle de una factura o boleta: anulaciones, devoluciones, descuentos, intereses y penalidades." />

    <x-flash />

    <div class="mt-6 grid gap-4 sm:grid-cols-2">
        <x-stat-card title="Notas de crédito (vigentes)" icon="file-minus" :value="'S/ ' . number_format((float) ($totales['07']->total ?? 0), 2)">{{ (int) ($totales['07']->cantidad ?? 0) }} notas · restan a las ventas</x-stat-card>
        <x-stat-card title="Notas de débito (vigentes)" icon="plus-circle" tone="amber" :value="'S/ ' . number_format((float) ($totales['08']->total ?? 0), 2)">{{ (int) ($totales['08']->cantidad ?? 0) }} notas · suman a las ventas</x-stat-card>
    </div>

    <form method="GET" class="mt-6 grid items-end gap-4 rounded-xl border border-slate-200 bg-white p-4 shadow-sm sm:grid-cols-2 sm:p-5 lg:grid-cols-[2fr_1fr_1fr_1fr_1fr_auto]">
        <div class="sm:col-span-2 lg:col-span-1">
            <div class="flex items-center justify-between">
                <label for="q" class="field-label !mb-0">Buscar</label>
                @if ($hayFiltros)<a href="{{ route('notas.index') }}" class="text-xs font-semibold text-brand-600">Limpiar</a>@endif
            </div>
            <input id="q" name="q" type="search" value="{{ request('q') }}" placeholder="N.º de nota, comprobante o cliente..." class="field mt-1.5">
        </div>
        <x-select name="tipo" label="Tipo">
            <option value="">Todas</option>
            <option value="07" @selected(request('tipo') === '07')>Crédito</option>
            <option value="08" @selected(request('tipo') === '08')>Débito</option>
        </x-select>
        <x-select name="estado" label="Estado">
            <option value="">Todos</option>
            <option value="emitida" @selected(request('estado') === 'emitida')>Emitidas</option>
            <option value="anulada" @selected(request('estado') === 'anulada')>Anuladas</option>
        </x-select>
        <x-input name="desde" label="Desde" type="date" :value="request('desde')" />
        <x-input name="hasta" label="Hasta" type="date" :value="request('hasta')" />
        <button type="submit" class="btn btn-secondary"><x-icon name="sliders" class="text-brand-600" /> Filtrar</button>
    </form>

    <section class="mt-6 overflow-x-auto rounded-xl border border-slate-200 bg-white shadow-sm">
        @if ($notas->isEmpty())
            <x-empty-state icon="file-minus" title="Sin notas" description="Para emitir una, abre una factura o boleta y elige «Nota de crédito» o «Nota de débito»." />
        @else
            <table class="w-full text-sm">
                <thead class="border-b border-slate-200 bg-slate-50/70 text-left text-xs font-medium text-slate-500">
                    <tr><th class="py-3 pl-5 pr-3">Nota</th><th class="px-3 py-3">Comprobante</th><th class="px-3 py-3">Cliente</th><th class="px-3 py-3">Motivo</th><th class="px-3 py-3">Fecha</th><th class="px-3 py-3 text-right">Total</th><th class="py-3 pl-3 pr-5">Estado</th></tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach ($notas as $n)
                        <tr class="hover:bg-slate-50/60">
                            <td class="py-3 pl-5 pr-3">
                                <a href="{{ route('notas.show', $n) }}" class="font-semibold text-brand-600 hover:text-brand-700">{{ $n->numero_completo }}</a>
                                <p class="text-xs text-slate-500">{{ $n->tipo_etiqueta }}</p>
                            </td>
                            <td class="px-3 py-3"><a href="{{ route('ventas.show', $n->venta_id) }}" class="hover:text-brand-600">{{ $n->venta?->numero_completo }}</a></td>
                            <td class="px-3 py-3">{{ $n->venta?->cliente?->nombre_completo ?? 'Cliente varios' }}</td>
                            <td class="px-3 py-3 text-slate-600">{{ $n->motivo_descripcion }}</td>
                            <td class="whitespace-nowrap px-3 py-3">{{ $n->fecha_emision->format('d/m/Y') }}</td>
                            <td @class(['px-3 py-3 text-right font-semibold', 'line-through text-slate-400' => $n->esAnulada()])>{{ $n->esCredito() ? '−' : '+' }} <x-money :value="$n->total" /></td>
                            <td class="py-3 pl-3 pr-5"><x-badge :tone="$n->esAnulada() ? 'neutral' : 'success'" dot>{{ $n->esAnulada() ? 'Anulada' : 'Emitida' }}</x-badge></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            @if ($notas->hasPages())
                <div class="border-t border-slate-200 px-4 py-3 sm:px-5">{{ $notas->links('vendor.pagination.ricopet') }}</div>
            @endif
        @endif
    </section>
@endsection
