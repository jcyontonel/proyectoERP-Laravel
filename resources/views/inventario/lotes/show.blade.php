{{-- LoteController@show: $lote (producto, existencias.almacen), $movimientos (kardex del lote) --}}
@extends('layouts.app')

@use('App\Support\Unidades')

@php $d = $lote->fecha_vencimiento ? (int) today()->diffInDays($lote->fecha_vencimiento, false) : null; @endphp

@section('title', 'Lote ' . $lote->codigo_lote)

@section('content')
    <x-page-header :title="'Lote ' . $lote->codigo_lote" :migas="['Inventario' => null, 'Lotes' => route('lotes.index'), $lote->codigo_lote => null]"
        :subtitle="$lote->producto?->nombre">
        <x-slot:badge>
            @if ($d !== null && $d < 0)
                <x-badge tone="danger" shape="box">Vencido hace {{ abs($d) }} días</x-badge>
            @elseif ($d !== null)
                <x-badge :tone="$d <= 30 ? 'warning' : 'success'" shape="box">Vence en {{ $d }} días</x-badge>
            @endif
        </x-slot:badge>
        <x-slot:acciones>
            <a href="{{ route('ajustes.create', ['tipo' => 'egreso']) }}" class="btn btn-secondary"><x-icon name="sliders" class="text-brand-600" /> Registrar salida</a>
        </x-slot:acciones>
    </x-page-header>

    <x-flash />

    <div class="mt-6 grid gap-5 lg:grid-cols-[minmax(0,1fr)_20rem]">
        <x-card title="Movimientos del lote" icon="layers">
            @if ($movimientos->isEmpty())
                <p class="text-sm text-slate-500">Sin movimientos registrados.</p>
            @else
                <div class="-mx-4 overflow-x-auto sm:-mx-5">
                    <table class="w-full text-sm">
                        <thead class="border-y border-slate-200 bg-slate-50/70 text-left text-xs font-medium text-slate-500">
                            <tr>
                                <th class="py-2.5 pl-4 pr-3 sm:pl-5">Fecha</th>
                                <th class="px-3 py-2.5">Documento</th>
                                <th class="px-3 py-2.5">Almacén</th>
                                <th class="py-2.5 pl-3 pr-4 text-right sm:pr-5">Cantidad</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach ($movimientos as $m)
                                @php $entra = $m->tipo_movimiento === 'ingreso'; @endphp
                                <tr>
                                    <td class="whitespace-nowrap py-2.5 pl-4 pr-3 text-slate-600 sm:pl-5">{{ $m->fecha_movimiento?->format('d/m/Y H:i') }}</td>
                                    <td class="px-3 py-2.5">@if ($m->referenciaUrl())<a href="{{ $m->referenciaUrl() }}" class="hover:text-brand-600">{{ $m->referencia_label }}</a>@else{{ $m->referencia_label }}@endif</td>
                                    <td class="px-3 py-2.5 text-slate-600">{{ $m->almacen?->nombre }}</td>
                                    <td @class(['py-2.5 pl-3 pr-4 text-right font-semibold sm:pr-5', 'text-emerald-700' => $entra, 'text-red-600' => ! $entra])>{{ $entra ? '+' : '−' }}{{ Unidades::cantidad($m->cantidad) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </x-card>

        <aside class="space-y-5">
            <x-card title="Stock por almacén" icon="warehouse">
                @forelse ($lote->existencias as $e)
                    <div class="flex justify-between border-b border-slate-100 py-2 text-sm last:border-0">
                        <span>{{ $e->almacen?->nombre }}</span>
                        <span class="font-semibold">{{ Unidades::conUnidad($e->cantidad, $lote->producto?->tipoUnidad?->descripcion) }}</span>
                    </div>
                @empty
                    <p class="text-sm text-slate-500">Sin existencias.</p>
                @endforelse
            </x-card>

            <x-card title="Datos del lote" icon="calendar-clock">
                <form method="POST" action="{{ route('lotes.update', $lote) }}" class="space-y-4">
                    @csrf @method('PUT')
                    <x-input name="fecha_fabricacion" label="Fabricación" type="date" :value="$lote->fecha_fabricacion?->format('Y-m-d')" />
                    <x-input name="fecha_vencimiento" label="Vencimiento" type="date" :value="$lote->fecha_vencimiento?->format('Y-m-d')" />
                    <x-toggle name="activo" label="Activo" :checked="$lote->activo" hint="Un lote inactivo no se ofrece en salidas." />
                    <button type="submit" class="btn btn-primary w-full"><x-icon name="save" /> Guardar</button>
                </form>
            </x-card>
        </aside>
    </div>
@endsection
