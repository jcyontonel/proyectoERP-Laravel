{{-- KardexController@index: $movimientos, $totales {entradas, salidas, valor}, $producto (filtrado), $productos, $almacenes, $tipos --}}
@extends('layouts.app')

@use('App\Modulos\Catalogo\Support\Unidades')

@section('title', 'Kardex')

@section('content')
    <x-page-header title="Kardex" :migas="['Inventario' => null, 'Kardex' => null]"
        :subtitle="$producto ? 'Movimientos de ' . $producto->nombre . ' (' . ($producto->tipoUnidad?->descripcion ?? 'unidad') . ')' : 'Historial valorizado de entradas y salidas de stock por producto, almacén y documento.'">
        <x-slot:acciones>
            <a href="{{ route('kardex.export', request()->query()) }}" class="btn btn-secondary"><x-icon name="download" class="text-brand-600" /> Exportar CSV</a>
        </x-slot:acciones>
    </x-page-header>

    <x-flash />

    <form method="GET" class="mt-6 grid items-end gap-4 rounded-xl border border-slate-200 bg-white p-4 shadow-sm sm:grid-cols-3 sm:p-5 lg:grid-cols-[1.6fr_1fr_1fr_1fr_1fr_1fr_auto]">
        <x-select name="producto_id" label="Producto">
            <option value="">Todos</option>
            @foreach ($productos as $p)<option value="{{ $p->id }}" @selected(request('producto_id') == $p->id)>{{ $p->nombre }}{{ $p->codigo ? ' · ' . $p->codigo : '' }}</option>@endforeach
        </x-select>
        <x-select name="almacen_id" label="Almacén">
            <option value="">Todos</option>
            @foreach ($almacenes as $a)<option value="{{ $a->id }}" @selected(request('almacen_id') == $a->id)>{{ $a->nombre }}</option>@endforeach
        </x-select>
        <x-select name="tipo" label="Documento">
            <option value="">Todos</option>
            @foreach ($tipos as $v => $e)<option value="{{ $v }}" @selected(request('tipo') === $v)>{{ $e }}</option>@endforeach
        </x-select>
        <x-input name="q" label="N.º de documento" :value="request('q')" />
        <x-input name="desde" label="Desde" type="date" :value="request('desde')" />
        <x-input name="hasta" label="Hasta" type="date" :value="request('hasta')" />
        <button type="submit" class="btn btn-secondary"><x-icon name="sliders" class="text-brand-600" /> Filtrar</button>
    </form>

    <section class="mt-6 grid grid-cols-2 gap-4 xl:grid-cols-3">
        <x-stat-card title="Entradas" icon="trending-up" tone="emerald" :value="Unidades::cantidad($totales->entradas)"><span>{{ $producto ? $producto->tipoUnidad?->descripcion : 'unidades (varias unidades)' }}</span></x-stat-card>
        <x-stat-card title="Salidas" icon="trending-down" tone="red" :value="Unidades::cantidad($totales->salidas)"><span>{{ $producto ? $producto->tipoUnidad?->descripcion : 'unidades (varias unidades)' }}</span></x-stat-card>
        <x-stat-card title="Valor neto movido" icon="pie-chart" tone="slate" :value="'S/ ' . number_format($totales->valor, 2)" class="col-span-2 xl:col-span-1"><span>entradas − salidas, al costo</span></x-stat-card>
    </section>

    <section class="mt-6 overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
        @if ($movimientos->isEmpty())
            <x-empty-state icon="layers" title="Sin movimientos" description="Las compras, ventas, ajustes y transferencias aparecerán aquí." />
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="border-b border-slate-200 bg-slate-50/70 text-left text-xs font-medium text-slate-500">
                        <tr>
                            <th class="py-3 pl-5 pr-3">Fecha</th>
                            @unless ($producto)<th class="px-3 py-3">Producto</th>@endunless
                            <th class="px-3 py-3">Documento</th>
                            <th class="px-3 py-3">Almacén</th>
                            <th class="px-3 py-3 text-right">Entrada</th>
                            <th class="px-3 py-3 text-right">Salida</th>
                            <th class="px-3 py-3 text-right">Saldo</th>
                            <th class="px-3 py-3 text-right">Costo unit.</th>
                            <th class="py-3 pl-3 pr-5 text-right">Costo total</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($movimientos as $m)
                            @php $entra = $m->tipo_movimiento === 'ingreso'; $url = $m->referenciaUrl(); @endphp
                            <tr class="hover:bg-slate-50/60">
                                <td class="whitespace-nowrap py-2.5 pl-5 pr-3 text-slate-600">{{ $m->fecha_movimiento?->format('d/m/Y H:i') }}</td>
                                @unless ($producto)
                                    <td class="px-3 py-2.5">
                                        <a href="{{ route('kardex.index', ['producto_id' => $m->producto_id] + request()->except('producto_id', 'page')) }}" class="font-medium hover:text-brand-600">{{ $m->producto?->nombre }}</a>
                                        <p class="text-[11px] text-slate-500">{{ $m->producto?->tipoUnidad?->descripcion }}</p>
                                    </td>
                                @endunless
                                <td class="px-3 py-2.5">
                                    @if ($url)<a href="{{ $url }}" class="hover:text-brand-600">{{ $m->referencia_label }}</a>@else{{ $m->referencia_label }}@endif
                                    @if ($m->lote)<p class="text-[11px] text-slate-500">Lote {{ $m->lote->codigo_lote }}</p>@endif
                                </td>
                                <td class="px-3 py-2.5 text-slate-600">{{ $m->almacen?->nombre }}</td>
                                <td class="px-3 py-2.5 text-right font-medium text-emerald-700">{{ $entra ? Unidades::cantidad($m->cantidad) : '' }}</td>
                                <td class="px-3 py-2.5 text-right font-medium text-red-600">{{ $entra ? '' : Unidades::cantidad($m->cantidad) }}</td>
                                <td class="px-3 py-2.5 text-right font-semibold">{{ Unidades::cantidad($m->stock_nuevo) }}</td>
                                <td class="px-3 py-2.5 text-right text-slate-600">{{ $m->costo_unitario !== null ? number_format($m->costo_unitario, 2) : '—' }}</td>
                                <td class="py-2.5 pl-3 pr-5 text-right">{{ $m->costo_total !== null ? number_format($m->costo_total, 2) : '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="border-t border-slate-200 px-4 py-3 sm:px-5">{{ $movimientos->links() }}</div>
        @endif
    </section>
@endsection
