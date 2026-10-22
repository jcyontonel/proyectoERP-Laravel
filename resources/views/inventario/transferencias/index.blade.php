{{-- TransferenciaController@index: $transferencias, $almacenes, $enTransito --}}
@extends('layouts.app')

@section('title', 'Transferencias')

@section('content')
    <x-page-header title="Transferencias entre almacenes" :migas="['Inventario' => null, 'Transferencias' => null]"
        subtitle="Envía mercadería de un almacén a otro: sale del origen al enviar y entra al destino al recibir.">
        <x-slot:acciones>
            <a href="{{ route('transferencias.create') }}" class="btn btn-primary"><x-icon name="plus" /> Nueva transferencia</a>
        </x-slot:acciones>
    </x-page-header>

    <x-flash />

    @if ($enTransito)
        <x-alert tone="info" class="mt-4">
            {{ $enTransito }} {{ $enTransito === 1 ? 'transferencia está' : 'transferencias están' }} en tránsito, esperando ser recibidas.
            <a href="{{ route('transferencias.index', ['estado' => 'en_transito']) }}" class="font-semibold underline">Ver</a>
        </x-alert>
    @endif

    <form method="GET" class="mt-6 grid items-end gap-4 rounded-xl border border-slate-200 bg-white p-4 shadow-sm sm:grid-cols-3 sm:p-5 lg:grid-cols-[1.2fr_1fr_1fr_1fr_1fr_auto]">
        <x-input name="q" label="N.º u observación" :value="request('q')" />
        <x-select name="estado" label="Estado">
            <option value="">Todos</option>
            @foreach (\App\Models\TransferenciaAlmacen::ESTADOS as $v => $e)<option value="{{ $v }}" @selected(request('estado') === $v)>{{ $e }}</option>@endforeach
        </x-select>
        <x-select name="almacen_id" label="Almacén (origen o destino)">
            <option value="">Todos</option>
            @foreach ($almacenes as $a)<option value="{{ $a->id }}" @selected(request('almacen_id') == $a->id)>{{ $a->nombre }}</option>@endforeach
        </x-select>
        <x-input name="desde" label="Desde" type="date" :value="request('desde')" />
        <x-input name="hasta" label="Hasta" type="date" :value="request('hasta')" />
        <button type="submit" class="btn btn-secondary"><x-icon name="sliders" class="text-brand-600" /> Filtrar</button>
    </form>

    <section class="mt-6 overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
        @if ($transferencias->isEmpty())
            <x-empty-state icon="arrow-left-right" title="No hay transferencias" description="Mueve mercadería entre tus almacenes con una nueva transferencia." />
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="border-b border-slate-200 bg-slate-50/70 text-left text-xs font-medium text-slate-500">
                        <tr>
                            <th class="py-3 pl-5 pr-3">Transferencia</th>
                            <th class="px-3 py-3">Origen → destino</th>
                            <th class="px-3 py-3 text-right">Líneas</th>
                            <th class="px-3 py-3">Usuario</th>
                            <th class="py-3 pl-3 pr-5">Estado</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($transferencias as $t)
                            <tr class="hover:bg-slate-50/60">
                                <td class="py-3 pl-5 pr-3">
                                    <a href="{{ route('transferencias.show', $t) }}" class="font-semibold text-brand-600 hover:text-brand-700">{{ $t->numero_completo }}</a>
                                    <p class="text-xs text-slate-500">{{ $t->fecha_movimiento->format('d/m/Y') }}</p>
                                </td>
                                <td class="px-3 py-3">{{ $t->origen?->nombre }} <span class="text-slate-400">→</span> {{ $t->destino?->nombre }}</td>
                                <td class="px-3 py-3 text-right">{{ $t->detalles_count }}</td>
                                <td class="px-3 py-3">{{ $t->usuario?->nombre_completo }}</td>
                                <td class="py-3 pl-3 pr-5"><x-estado-inventario :estado="$t->estado" /></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="border-t border-slate-200 px-4 py-3 sm:px-5">{{ $transferencias->links() }}</div>
        @endif
    </section>
@endsection
