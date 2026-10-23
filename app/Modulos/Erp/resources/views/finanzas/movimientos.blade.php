{{--
    Historial de cobros y pagos (MovimientoFinanzasController@index)
      $pagos     LengthAwarePaginator de Pago (venta.cliente, compra.proveedor, usuario, sesion)
      $cobrado   suma de cobros vigentes según filtros
      $pagado    suma de pagos vigentes según filtros
      $verPagos  bool: el usuario maneja cuentas por pagar
      $metodos   [valor => etiqueta]
    Filtros (GET): q, tipo, metodo, estado, desde, hasta
--}}
@extends('layouts.app')

@section('title', 'Cobros y pagos')

@php $hayFiltros = request()->hasAny(['q', 'tipo', 'metodo', 'estado', 'desde', 'hasta']); @endphp

@section('content')
    <x-page-header title="Cobros y pagos" subtitle="Todo el dinero que entra por ventas y sale hacia proveedores, con su documento, método y responsable."
        :migas="['Finanzas' => null, 'Cobros y pagos' => null]">
        <x-slot:acciones>
            <a href="{{ route('cuentas-cobrar.index') }}" class="btn btn-secondary"><x-icon name="coins" class="text-brand-600" /> Por cobrar</a>
            @if ($verPagos)
                <a href="{{ route('cuentas-pagar.index') }}" class="btn btn-secondary"><x-icon name="landmark" class="text-brand-600" /> Por pagar</a>
            @endif
        </x-slot:acciones>
    </x-page-header>

    <x-flash class="mt-4" />

    <div @class(['mt-6 grid gap-4', 'sm:grid-cols-3' => $verPagos, 'sm:grid-cols-1' => ! $verPagos])>
        <x-stat-card title="Cobrado" icon="trending-up" tone="emerald" :value="'S/ ' . number_format($cobrado, 2)">Según los filtros · sin anulados</x-stat-card>
        @if ($verPagos)
            <x-stat-card title="Pagado a proveedores" icon="trending-down" tone="red" :value="'S/ ' . number_format($pagado, 2)">Según los filtros · sin anulados</x-stat-card>
            <x-stat-card title="Flujo neto" icon="wallet" :tone="$cobrado - $pagado >= 0 ? 'brand' : 'amber'" :value="'S/ ' . number_format($cobrado - $pagado, 2)">Cobrado − pagado</x-stat-card>
        @endif
    </div>

    <form method="GET" action="{{ route('finanzas.movimientos.index') }}" class="mt-6 rounded-xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5">
        <div class="flex items-center justify-between">
            <label for="q" class="field-label !mb-0">Buscar</label>
            @if ($hayFiltros)
                <a href="{{ route('finanzas.movimientos.index') }}" class="text-xs font-semibold text-brand-600 hover:text-brand-700">Limpiar filtros</a>
            @endif
        </div>
        <div class="relative mt-1.5">
            <x-icon name="search" class="pointer-events-none absolute left-3 top-3 text-slate-400" />
            <input id="q" name="q" type="search" value="{{ request('q') }}" autocomplete="off" placeholder="Cliente, proveedor, DNI/RUC o N.º de operación..." class="field pl-9">
        </div>

        <div class="mt-4 grid grid-cols-2 items-end gap-4 lg:grid-cols-[1fr_1fr_1fr_1.5fr_auto_auto]">
            @if ($verPagos)
                <x-select name="tipo" label="Tipo">
                    <option value="">Todos</option>
                    <option value="cobro" @selected(request('tipo') === 'cobro')>Cobros</option>
                    <option value="pago" @selected(request('tipo') === 'pago')>Pagos</option>
                </x-select>
            @endif
            <x-select name="metodo" label="Método">
                <option value="">Todos</option>
                @foreach ($metodos as $valor => $etiqueta)
                    <option value="{{ $valor }}" @selected(request('metodo') === $valor)>{{ $etiqueta }}</option>
                @endforeach
            </x-select>
            <x-select name="estado" label="Estado">
                <option value="">Todos</option>
                <option value="registrado" @selected(request('estado') === 'registrado')>Vigentes</option>
                <option value="anulado" @selected(request('estado') === 'anulado')>Anulados</option>
            </x-select>
            <div class="col-span-2 lg:col-span-1">
                <span class="field-label">Rango de fechas</span>
                <div class="field flex items-center gap-2 px-3 focus-within:border-brand-500 focus-within:ring-2 focus-within:ring-brand-500/20">
                    <x-icon name="calendar" class="text-slate-400" />
                    <input type="date" name="desde" value="{{ request('desde') }}" aria-label="Desde" class="min-w-0 flex-1 border-0 bg-transparent p-0 text-sm focus:outline-none">
                    <span class="text-slate-400" aria-hidden="true">–</span>
                    <input type="date" name="hasta" value="{{ request('hasta') }}" aria-label="Hasta" class="min-w-0 flex-1 border-0 bg-transparent p-0 text-sm focus:outline-none">
                </div>
            </div>
            <button type="submit" class="btn btn-secondary w-full"><x-icon name="sliders" class="text-brand-600" /> Filtrar</button>
            <a href="{{ route('finanzas.movimientos.export', request()->except('page')) }}" class="btn btn-secondary w-full whitespace-nowrap"><x-icon name="download" class="text-brand-600" /> Exportar CSV</a>
        </div>
    </form>

    <section class="mt-6 rounded-xl border border-slate-200 bg-white shadow-sm" aria-labelledby="titulo-listado">
        <header class="p-4 sm:p-5">
            <h2 id="titulo-listado" class="text-base font-semibold">Movimientos</h2>
            <p class="mt-0.5 text-xs text-slate-500">{{ number_format($pagos->total()) }} registros · Del más reciente al más antiguo</p>
        </header>

        @if ($pagos->isEmpty())
            <x-empty-state class="border-t border-slate-200" icon="wallet" title="Sin movimientos" description="Prueba con otros filtros." />
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="border-y border-slate-200 bg-slate-50/70 text-left text-xs font-medium text-slate-500">
                        <tr>
                            <th class="py-3 pl-5 pr-3">Fecha</th>
                            <th class="px-3 py-3">Tipo</th>
                            <th class="px-3 py-3">Documento</th>
                            <th class="px-3 py-3">Cliente / proveedor</th>
                            <th class="px-3 py-3">Método</th>
                            <th class="px-3 py-3 text-right">Monto</th>
                            <th class="px-3 py-3">Registrado por</th>
                            <th class="py-3 pl-3 pr-5"><span class="sr-only">Acciones</span></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($pagos as $p)
                            @php
                                $anulado = $p->estado !== 'registrado';
                                $esCobro = $p->tipo === 'cobro';
                                $doc = $esCobro ? $p->venta : $p->compra;
                                $tercero = $esCobro ? $p->venta?->cliente?->nombre_completo : $p->compra?->proveedor?->nombre;
                            @endphp
                            <tr @class(['hover:bg-slate-50/60', 'text-slate-400' => $anulado])>
                                <td class="whitespace-nowrap py-3 pl-5 pr-3">
                                    {{ $p->fecha_pago->translatedFormat('d M Y') }}
                                    <p class="text-xs text-slate-500">{{ $p->fecha_pago->format('H:i') }}</p>
                                </td>
                                <td class="px-3 py-3">
                                    @if ($anulado)
                                        <x-badge tone="neutral" shape="box">Anulado</x-badge>
                                    @else
                                        <x-badge :tone="$esCobro ? 'success' : 'danger'" shape="box">{{ $esCobro ? 'Cobro' : 'Pago' }}</x-badge>
                                    @endif
                                </td>
                                <td class="px-3 py-3">
                                    @if ($doc)
                                        <a href="{{ route($esCobro ? 'ventas.show' : 'compras.show', $doc) }}" class="font-medium text-brand-600 hover:text-brand-700">{{ $doc->numero_completo }}</a>
                                    @else
                                        —
                                    @endif
                                    @if ($p->observacion)<p class="max-w-56 truncate text-xs text-slate-500" title="{{ $p->observacion }}">{{ $p->observacion }}</p>@endif
                                </td>
                                <td class="px-3 py-3">{{ $tercero ?? '—' }}</td>
                                <td class="px-3 py-3">
                                    <x-metodo-pago :metodo="$p->metodo_pago" :label="$metodos[$p->metodo_pago] ?? $p->metodo_pago" />
                                    @if ($p->referencia)<p class="text-xs text-slate-500">Op. {{ $p->referencia }}</p>@endif
                                </td>
                                <td @class(['px-3 py-3 text-right font-semibold', 'line-through' => $anulado, 'text-emerald-700' => ! $anulado && $esCobro])>
                                    {{ $esCobro ? '' : '− ' }}<x-money :value="$p->monto" />
                                </td>
                                <td class="px-3 py-3 text-slate-600">
                                    {{ $p->usuario?->name ?? '—' }}
                                    @if ($p->sesion)<p class="text-xs text-slate-500">Turno de caja #{{ $p->sesion->id }}</p>@endif
                                </td>
                                <td class="py-3 pl-3 pr-5 text-right">
                                    @if (! $anulado && (! $p->sesion || $p->sesion->estaAbierta()) && ($esCobro || $verPagos))
                                        <button type="button" data-anular-pago="{{ route('finanzas.movimientos.anular', $p) }}"
                                            data-numero="{{ $esCobro ? 'cobro' : 'pago' }} de S/ {{ number_format($p->monto, 2) }} ({{ $doc?->numero_completo }})"
                                            title="Anular" aria-label="Anular movimiento" class="grid size-8 place-items-center rounded-md text-red-500 hover:bg-red-50"><x-icon name="x-circle" /></button>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="border-t border-slate-200 px-4 py-3 sm:px-5">{{ $pagos->links('vendor.pagination.ricopet') }}</div>
        @endif
    </section>

    @include('finanzas.partials.dialogo-anular-pago')
@endsection
