{{-- MovimientoCajaController@index: $movimientos (paginador), $totales [ingreso, egreso], $cajas --}}
@extends('layouts.app')

@section('title', 'Ingresos y egresos')

@section('content')
    <x-page-header title="Ingresos y egresos" :migas="['Caja' => route('caja.index'), 'Ingresos y egresos' => null]"
        subtitle="Movimientos manuales de caja: gastos, retiros, fondos adicionales. Los cobros de ventas están en cada turno.">
        <x-slot:acciones>
            <a href="{{ route('caja.index') }}" class="btn btn-primary"><x-icon name="wallet" /> Mi caja</a>
        </x-slot:acciones>
    </x-page-header>

    <x-flash />

    <section class="mt-6 grid grid-cols-2 gap-4">
        <x-stat-card title="Ingresos" icon="trending-up" tone="emerald" :value="'S/ ' . number_format($totales['ingreso'], 2)"><span>según los filtros</span></x-stat-card>
        <x-stat-card title="Egresos" icon="trending-down" tone="red" :value="'S/ ' . number_format($totales['egreso'], 2)"><span>según los filtros</span></x-stat-card>
    </section>

    <form method="GET" class="mt-6 grid items-end gap-4 rounded-xl border border-slate-200 bg-white p-4 shadow-sm sm:grid-cols-2 sm:p-5 lg:grid-cols-[1.5fr_1fr_1fr_1fr_1fr_auto]">
        <x-input name="q" label="Concepto" :value="request('q')" placeholder="Buscar concepto…" />
        <x-select name="tipo" label="Tipo">
            <option value="">Todos</option>
            <option value="ingreso" @selected(request('tipo') === 'ingreso')>Ingresos</option>
            <option value="egreso" @selected(request('tipo') === 'egreso')>Egresos</option>
        </x-select>
        <x-select name="caja_id" label="Caja">
            <option value="">Todas</option>
            @foreach ($cajas as $c)<option value="{{ $c->id }}" @selected(request('caja_id') == $c->id)>{{ $c->nombre }}</option>@endforeach
        </x-select>
        <x-input name="desde" label="Desde" type="date" :value="request('desde')" />
        <x-input name="hasta" label="Hasta" type="date" :value="request('hasta')" />
        <button type="submit" class="btn btn-secondary"><x-icon name="sliders" class="text-brand-600" /> Filtrar</button>
    </form>

    <section class="mt-6 overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
        @if ($movimientos->isEmpty())
            <x-empty-state icon="arrow-left-right" title="Sin movimientos" description="Registra ingresos y egresos desde tu caja abierta." />
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="border-b border-slate-200 bg-slate-50/70 text-left text-xs font-medium text-slate-500">
                        <tr>
                            <th class="py-3 pl-5 pr-3">Fecha</th>
                            <th class="px-3 py-3">Concepto</th>
                            <th class="px-3 py-3">Caja / usuario</th>
                            <th class="px-3 py-3">Método</th>
                            <th class="py-3 pl-3 pr-5 text-right">Monto</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($movimientos as $m)
                            <tr @class(['opacity-50' => $m->esAnulado()])>
                                <td class="whitespace-nowrap py-3 pl-5 pr-3 text-slate-600">{{ $m->fecha_movimiento->format('d/m/Y H:i') }}</td>
                                <td class="px-3 py-3">
                                    <p class="font-medium">{{ $m->concepto }}</p>
                                    @if ($m->observacion)<p class="text-xs text-slate-500">{{ $m->observacion }}</p>@endif
                                    @if ($m->esAnulado())<x-badge tone="danger" shape="box">Anulado</x-badge>@endif
                                </td>
                                <td class="px-3 py-3">
                                    <a href="{{ route('caja.turnos.show', $m->caja_sesion_id) }}" class="hover:text-brand-600">{{ $m->sesion?->caja?->nombre }}</a>
                                    <p class="text-xs text-slate-500">{{ $m->usuario?->nombre_completo }}</p>
                                </td>
                                <td class="px-3 py-3"><x-metodo-pago :metodo="$m->metodo_pago" :label="config('ventas.metodos_pago')[$m->metodo_pago] ?? $m->metodo_pago" /></td>
                                <td @class(['py-3 pl-3 pr-5 text-right font-semibold', 'text-emerald-700' => $m->esIngreso(), 'text-red-600' => ! $m->esIngreso()])>
                                    {{ $m->esIngreso() ? '+' : '−' }} <x-money :value="$m->monto" />
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="border-t border-slate-200 px-4 py-3 sm:px-5">{{ $movimientos->links() }}</div>
        @endif
    </section>
@endsection
