{{-- TurnoCajaController@index: $turnos (paginador, cobrado), $todos (ve todos los usuarios), $cajas, $usuarios --}}
@extends('layouts.app')

@section('title', 'Historial de turnos')

@section('content')
    <x-page-header title="Historial de turnos" :migas="['Caja' => route('caja.index'), 'Historial de turnos' => null]"
        :subtitle="$todos ? 'Turnos de caja de todos los usuarios de la empresa.' : 'Tus turnos de caja: aperturas, cierres y arqueos.'">
        <x-slot:acciones>
            <a href="{{ route('caja.index') }}" class="btn btn-primary"><x-icon name="wallet" /> Mi caja</a>
        </x-slot:acciones>
    </x-page-header>

    <x-flash />

    <form method="GET" class="mt-6 grid items-end gap-4 rounded-xl border border-slate-200 bg-white p-4 shadow-sm sm:grid-cols-2 sm:p-5 lg:grid-cols-[1fr_1fr_1fr_1fr_1fr_auto]">
        <x-select name="caja_id" label="Caja">
            <option value="">Todas</option>
            @foreach ($cajas as $c)<option value="{{ $c->id }}" @selected(request('caja_id') == $c->id)>{{ $c->nombre }}</option>@endforeach
        </x-select>
        @if ($todos)
            <x-select name="user_id" label="Usuario">
                <option value="">Todos</option>
                @foreach ($usuarios as $u)<option value="{{ $u->id }}" @selected(request('user_id') == $u->id)>{{ $u->nombre_completo }}</option>@endforeach
            </x-select>
        @endif
        <x-select name="estado" label="Estado">
            <option value="">Todos</option>
            <option value="abierta" @selected(request('estado') === 'abierta')>Abiertos</option>
            <option value="cerrada" @selected(request('estado') === 'cerrada')>Cerrados</option>
        </x-select>
        <x-input name="desde" label="Desde" type="date" :value="request('desde')" />
        <x-input name="hasta" label="Hasta" type="date" :value="request('hasta')" />
        <button type="submit" class="btn btn-secondary"><x-icon name="sliders" class="text-brand-600" /> Filtrar</button>
    </form>

    <section class="mt-6 overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
        @if ($turnos->isEmpty())
            <x-empty-state icon="history" title="No hay turnos" description="Los turnos de caja aparecerán aquí al abrirlos." />
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="border-b border-slate-200 bg-slate-50/70 text-left text-xs font-medium text-slate-500">
                        <tr>
                            <th class="py-3 pl-5 pr-3">Turno</th>
                            <th class="px-3 py-3">Usuario</th>
                            <th class="px-3 py-3 text-right">Apertura</th>
                            <th class="px-3 py-3 text-right">Cobrado</th>
                            <th class="px-3 py-3 text-right">Contado</th>
                            <th class="px-3 py-3 text-right">Diferencia</th>
                            <th class="py-3 pl-3 pr-5">Estado</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($turnos as $t)
                            <tr class="hover:bg-slate-50/60">
                                <td class="py-3 pl-5 pr-3">
                                    <a href="{{ route('caja.turnos.show', $t) }}" class="font-semibold text-brand-600 hover:text-brand-700">{{ $t->caja?->nombre }}</a>
                                    <p class="text-xs text-slate-500">{{ $t->fecha_apertura->format('d/m/Y H:i') }}{{ $t->fecha_cierre ? ' – ' . $t->fecha_cierre->format('H:i') : '' }}</p>
                                </td>
                                <td class="px-3 py-3">{{ $t->usuario?->nombre_completo }}</td>
                                <td class="px-3 py-3 text-right"><x-money :value="$t->monto_apertura" /></td>
                                <td class="px-3 py-3 text-right font-semibold"><x-money :value="$t->cobrado ?? 0" /></td>
                                <td class="px-3 py-3 text-right">{{ $t->estaAbierta() ? '—' : 'S/ ' . number_format($t->monto_contado, 2) }}</td>
                                <td class="px-3 py-3 text-right">
                                    @if (! $t->estaAbierta())
                                        <span @class(['font-medium', 'text-emerald-700' => abs($t->diferencia) < 0.005, 'text-amber-700' => $t->diferencia > 0.004, 'text-red-600' => $t->diferencia < -0.004])>
                                            {{ abs($t->diferencia) < 0.005 ? 'Cuadra' : ($t->diferencia > 0 ? '+' : '−') . ' S/ ' . number_format(abs($t->diferencia), 2) }}
                                        </span>
                                    @else — @endif
                                </td>
                                <td class="py-3 pl-3 pr-5"><x-badge :tone="$t->estaAbierta() ? 'success' : 'neutral'" :dot="true">{{ $t->estaAbierta() ? 'Abierta' : 'Cerrada' }}</x-badge></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="border-t border-slate-200 px-4 py-3 sm:px-5">{{ $turnos->links() }}</div>
        @endif
    </section>
@endsection
