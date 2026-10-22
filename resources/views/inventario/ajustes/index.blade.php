{{-- AjusteInventarioController@index: $ajustes, $almacenes, $pendientes --}}
@extends('layouts.app')

@section('title', 'Ajustes de inventario')

@section('content')
    <x-page-header title="Ajustes de inventario" :migas="['Inventario' => null, 'Ajustes' => null]"
        subtitle="Conteos físicos, mermas, vencidos, consumos y sobrantes. Al confirmar mueven el stock y quedan en el kardex.">
        <x-slot:acciones>
            <a href="{{ route('ajustes.create', ['tipo' => 'conteo']) }}" class="btn btn-secondary"><x-icon name="list-checks" class="text-brand-600" /> Conteo físico</a>
            <a href="{{ route('ajustes.create') }}" class="btn btn-primary"><x-icon name="plus" /> Nuevo ajuste</a>
        </x-slot:acciones>
    </x-page-header>

    <x-flash />

    @if ($pendientes)
        <x-alert tone="warning" class="mt-4">
            Hay {{ $pendientes }} {{ $pendientes === 1 ? 'ajuste esperando' : 'ajustes esperando' }} aprobación.
            <a href="{{ route('ajustes.index', ['estado' => 'pendiente']) }}" class="font-semibold underline">Ver pendientes</a>
        </x-alert>
    @endif

    <form method="GET" class="mt-6 grid items-end gap-4 rounded-xl border border-slate-200 bg-white p-4 shadow-sm sm:grid-cols-3 sm:p-5 lg:grid-cols-[1.2fr_1fr_1fr_1fr_1fr_1fr_auto]">
        <x-input name="q" label="N.º u observación" :value="request('q')" />
        <x-select name="tipo" label="Tipo">
            <option value="">Todos</option>
            @foreach (\App\Models\AjusteInventario::TIPOS as $v => $e)<option value="{{ $v }}" @selected(request('tipo') === $v)>{{ $e }}</option>@endforeach
        </x-select>
        <x-select name="estado" label="Estado">
            <option value="">Todos</option>
            @foreach (['borrador' => 'Borrador', 'pendiente' => 'Por aprobar', 'confirmado' => 'Confirmado', 'anulado' => 'Anulado'] as $v => $e)<option value="{{ $v }}" @selected(request('estado') === $v)>{{ $e }}</option>@endforeach
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
        @if ($ajustes->isEmpty())
            <x-empty-state icon="sliders" title="No hay ajustes" description="Registra un conteo físico o un ajuste por merma, vencimiento o sobrante." />
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="border-b border-slate-200 bg-slate-50/70 text-left text-xs font-medium text-slate-500">
                        <tr>
                            <th class="py-3 pl-5 pr-3">Ajuste</th>
                            <th class="px-3 py-3">Tipo / motivo</th>
                            <th class="px-3 py-3">Almacén</th>
                            <th class="px-3 py-3 text-right">Líneas</th>
                            <th class="px-3 py-3">Usuario</th>
                            <th class="py-3 pl-3 pr-5">Estado</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($ajustes as $a)
                            <tr class="hover:bg-slate-50/60">
                                <td class="py-3 pl-5 pr-3">
                                    <a href="{{ route('ajustes.show', $a) }}" class="font-semibold text-brand-600 hover:text-brand-700">{{ $a->numero_completo }}</a>
                                    <p class="text-xs text-slate-500">{{ $a->fecha_movimiento->format('d/m/Y') }}</p>
                                </td>
                                <td class="px-3 py-3">
                                    {{ $a->tipo_etiqueta }}
                                    <p class="text-xs text-slate-500">{{ $a->motivo?->nombre }}</p>
                                </td>
                                <td class="px-3 py-3">{{ $a->almacen?->nombre }}</td>
                                <td class="px-3 py-3 text-right">{{ $a->detalles_count }}</td>
                                <td class="px-3 py-3">{{ $a->usuario?->nombre_completo }}</td>
                                <td class="py-3 pl-3 pr-5"><x-estado-inventario :estado="$a->estado" /></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="border-t border-slate-200 px-4 py-3 sm:px-5">{{ $ajustes->links() }}</div>
        @endif
    </section>
@endsection
