{{-- LoteController@index: $lotes (cantidad, existencias.almacen), $almacenes, $dias, $resumen [vencidos, por_vencer, con_stock] --}}
@extends('layouts.app')

@use('App\Modulos\Catalogo\Support\Unidades')

@section('title', 'Lotes y vencimientos')

@section('content')
    <x-page-header title="Lotes y vencimientos" :migas="['Inventario' => null, 'Lotes y vencimientos' => null]"
        subtitle="Stock por lote y almacén. Vende primero lo que vence antes y retira lo vencido con un ajuste." />

    <x-flash />

    <section class="mt-6 grid grid-cols-2 gap-4 xl:grid-cols-3">
        <x-stat-card title="Vencidos con stock" icon="x-circle" tone="red" :value="number_format($resumen['vencidos'])" :href="route('lotes.index', ['vencimiento' => 'vencidos'])">
            <span>retíralos con un ajuste de salida</span>
        </x-stat-card>
        <x-stat-card title="Por vencer" icon="calendar-clock" tone="amber" :value="number_format($resumen['por_vencer'])" :href="route('lotes.index', ['vencimiento' => 'por_vencer'])">
            <span>en los próximos {{ $dias }} días</span>
        </x-stat-card>
        <x-stat-card title="Lotes con stock" icon="layers" tone="slate" :value="number_format($resumen['con_stock'])" class="col-span-2 xl:col-span-1">
            <span>en todos los almacenes</span>
        </x-stat-card>
    </section>

    <form method="GET" class="mt-6 grid items-end gap-4 rounded-xl border border-slate-200 bg-white p-4 shadow-sm sm:grid-cols-2 sm:p-5 lg:grid-cols-[1.6fr_1fr_1fr_1fr_auto]">
        <x-input name="q" label="Producto o lote" :value="request('q')" placeholder="Nombre, código o lote…" />
        <x-select name="vencimiento" label="Vencimiento">
            <option value="">Todos</option>
            @foreach (['vencidos' => 'Vencidos', 'por_vencer' => "Por vencer ({$dias} días)", 'vigentes' => 'Vigentes', 'sin_fecha' => 'Sin fecha'] as $v => $e)
                <option value="{{ $v }}" @selected(request('vencimiento') === $v)>{{ $e }}</option>
            @endforeach
        </x-select>
        <x-select name="almacen_id" label="Almacén">
            <option value="">Todos</option>
            @foreach ($almacenes as $a)<option value="{{ $a->id }}" @selected(request('almacen_id') == $a->id)>{{ $a->nombre }}</option>@endforeach
        </x-select>
        <x-select name="con_stock" label="Stock">
            <option value="1" @selected(request('con_stock', '1') === '1')>Con stock</option>
            <option value="0" @selected(request('con_stock') === '0')>Todos (incluye agotados)</option>
        </x-select>
        <button type="submit" class="btn btn-secondary"><x-icon name="sliders" class="text-brand-600" /> Filtrar</button>
    </form>

    <section class="mt-6 overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
        @if ($lotes->isEmpty())
            <x-empty-state icon="calendar-clock" title="Sin lotes" description="Los lotes se registran al comprar productos que controlan lotes y vencimiento." />
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="border-b border-slate-200 bg-slate-50/70 text-left text-xs font-medium text-slate-500">
                        <tr>
                            <th class="py-3 pl-5 pr-3">Lote</th>
                            <th class="px-3 py-3">Producto</th>
                            <th class="px-3 py-3">Por almacén</th>
                            <th class="px-3 py-3 text-right">Cantidad</th>
                            <th class="py-3 pl-3 pr-5">Vencimiento</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($lotes as $l)
                            @php $d = $l->fecha_vencimiento ? (int) today()->diffInDays($l->fecha_vencimiento, false) : null; @endphp
                            <tr class="hover:bg-slate-50/60">
                                <td class="py-3 pl-5 pr-3"><a href="{{ route('lotes.show', $l) }}" class="font-semibold text-brand-600 hover:text-brand-700">{{ $l->codigo_lote }}</a></td>
                                <td class="px-3 py-3">
                                    <a href="{{ route('productos.show', $l->producto_id) }}" class="hover:text-brand-600">{{ $l->producto?->nombre }}</a>
                                    <p class="text-xs text-slate-500">{{ $l->producto?->codigo }}</p>
                                </td>
                                <td class="px-3 py-3 text-xs text-slate-600">
                                    {{ $l->existencias->where('cantidad', '>', 0)->map(fn ($e) => $e->almacen?->nombre . ': ' . Unidades::cantidad($e->cantidad))->implode(' · ') ?: '—' }}
                                </td>
                                <td class="px-3 py-3 text-right font-semibold">{{ Unidades::conUnidad($l->cantidad ?? 0, $l->producto?->tipoUnidad?->descripcion) }}</td>
                                <td class="py-3 pl-3 pr-5">
                                    {{ $l->fecha_vencimiento?->format('d/m/Y') ?? 'Sin fecha' }}
                                    @if ($d !== null && $d < 0)
                                        <x-badge tone="danger" shape="box" class="ml-1">Vencido</x-badge>
                                    @elseif ($d !== null && $d <= $dias)
                                        <x-badge :tone="$d <= 7 ? 'danger' : 'warning'" shape="box" class="ml-1">{{ $d === 0 ? 'Hoy' : $d . ' días' }}</x-badge>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="border-t border-slate-200 px-4 py-3 sm:px-5">{{ $lotes->links() }}</div>
        @endif
    </section>
@endsection
