{{-- PagoController@index: $pagos (paginador), $totales [estado => cantidad, total], $catalogos. Filtros: estado, desde, hasta --}}
@extends('plataforma::layouts.app')

@section('title', 'Pagos')

@php
    $metodos = $catalogos['metodos_pago'];
    $hayFiltros = request()->hasAny(['estado', 'desde', 'hasta', 'empresa_id']);
@endphp

@section('content')
    <x-plataforma::page-header title="Pagos de suscripciones" :migas="['Pagos' => null]" subtitle="Cobros de todas las empresas: pendientes (renovaciones generadas solas), pagados y anulados.">
        <x-slot:acciones>
            <a href="{{ route('plataforma.pagos.export', request()->except('page')) }}" class="btn btn-secondary"><x-plataforma::icon name="download" class="text-brand-600" /> Exportar CSV</a>
        </x-slot:acciones>
    </x-plataforma::page-header>

    <x-plataforma::flash />

    <div class="mt-6 grid gap-4 sm:grid-cols-3">
        @foreach (['pagado' => 'emerald', 'pendiente' => 'amber', 'anulado' => 'slate'] as $estado => $tono)
            <x-plataforma::stat-card :title="config('plataforma.estados_pago')[$estado][1] . 's (según filtros)'" :icon="$estado === 'pagado' ? 'check-circle' : ($estado === 'pendiente' ? 'calendar-clock' : 'x-circle')" :tone="$tono"
                :value="'S/ ' . number_format($totales[$estado]['total'] ?? 0, 2)" :href="route('plataforma.pagos.index', ['estado' => $estado] + request()->only(['desde', 'hasta']))">
                {{ $totales[$estado]['cantidad'] ?? 0 }} {{ ($totales[$estado]['cantidad'] ?? 0) === 1 ? 'cobro' : 'cobros' }}
            </x-plataforma::stat-card>
        @endforeach
    </div>

    <form method="GET" class="mt-6 grid items-end gap-4 rounded-xl border border-slate-200 bg-white p-4 shadow-sm sm:grid-cols-[1fr_1fr_1fr_auto] sm:p-5">
        <x-plataforma::select name="estado" label="Estado">
            <option value="">Todos</option>
            @foreach (config('plataforma.estados_pago') as $valor => [, $etiqueta])<option value="{{ $valor }}" @selected(request('estado') === $valor)>{{ $etiqueta }}</option>@endforeach
        </x-plataforma::select>
        <x-plataforma::input name="desde" label="Desde" type="date" :value="request('desde')" />
        <x-plataforma::input name="hasta" label="Hasta" type="date" :value="request('hasta')" />
        <div class="flex gap-2">
            <button type="submit" class="btn btn-secondary"><x-plataforma::icon name="sliders" class="text-brand-600" /> Filtrar</button>
            @if ($hayFiltros)<a href="{{ route('plataforma.pagos.index') }}" class="btn btn-secondary" title="Limpiar"><x-plataforma::icon name="x" /></a>@endif
        </div>
    </form>

    <section class="mt-6 overflow-x-auto rounded-xl border border-slate-200 bg-white shadow-sm">
        @if ($pagos->isEmpty())
            <x-plataforma::empty-state icon="receipt" title="Sin pagos" description="No hay cobros con estos filtros." />
        @else
            <table class="w-full text-sm">
                <thead class="border-b border-slate-200 bg-slate-50/70 text-left text-xs font-medium text-slate-500">
                    <tr>
                        <th class="py-3 pl-5 pr-3">Empresa</th><th class="px-3 py-3">Plan</th><th class="px-3 py-3">Periodo</th>
                        <th class="px-3 py-3 text-right">Monto</th><th class="px-3 py-3">Estado</th><th class="px-3 py-3">Pago</th><th class="px-3 py-3">Comprobante</th><th class="py-3 pl-3 pr-5"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach ($pagos as $p)
                        <tr class="hover:bg-slate-50/60">
                            <td class="py-3 pl-5 pr-3"><a href="{{ route('plataforma.empresas.show', $p['empresa_id']) }}" class="font-medium hover:text-brand-600">{{ $p['empresa'] }}</a></td>
                            <td class="px-3 py-3">{{ $p['plan'] }} <span class="text-xs text-slate-500">{{ config('plataforma.periodicidades')[$p['periodicidad']] ?? '' }}</span></td>
                            <td class="whitespace-nowrap px-3 py-3">{{ date('d/m/Y', strtotime($p['periodo_desde'])) }} – {{ date('d/m/Y', strtotime($p['periodo_hasta'])) }}</td>
                            <td class="px-3 py-3 text-right font-semibold">
                                <x-plataforma::money :value="$p['monto']" />
                                @if ($p['descuento'] > 0)<p class="text-[11px] font-normal text-emerald-700">−<x-plataforma::money :value="$p['descuento']" /> {{ $p['oferta'] }}</p>@endif
                            </td>
                            <td class="px-3 py-3"><x-plataforma::estado tipo="pago" :valor="$p['estado']" /></td>
                            <td class="px-3 py-3 text-xs text-slate-600">
                                @if ($p['fecha_pago']){{ date('d/m/Y', strtotime($p['fecha_pago'])) }} · {{ $metodos[$p['metodo']] ?? $p['metodo'] }}@if ($p['referencia']) · {{ $p['referencia'] }}@endif @if ($p['registrado_por'])<span class="block text-slate-400">por {{ $p['registrado_por'] }}</span>@endif
                                @else — @endif
                            </td>
                            <td class="px-3 py-3 text-xs">@include('plataforma::pagos.partials.comprobante')</td>
                            <td class="py-3 pl-3 pr-5 text-right">
                                @if ($p['estado'] === 'pendiente')
                                    <a href="{{ route('plataforma.empresas.show', $p['empresa_id']) }}" class="text-xs font-semibold text-brand-600">Registrar pago</a>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            @if ($pagos->hasPages())
                <div class="border-t border-slate-200 px-4 py-3 sm:px-5">{{ $pagos->links('vendor.pagination.ricopet') }}</div>
            @endif
        @endif
    </section>
@endsection
