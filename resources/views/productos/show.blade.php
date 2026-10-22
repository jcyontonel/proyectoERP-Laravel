{{--
    Ficha del producto (ProductoController@show)
      $producto (categoria, tipoUnidad, impuesto, conversiones), $presentaciones, $stocks, $lotes,
      $movimientos (kardex), $ventas30 {cantidad, importe, ventas}, $ultimaCompra (DetalleCompra|null),
      $empresa, $verCostos
--}}
@extends('layouts.app')

@use('App\Support\Unidades')

@php
    $unidad      = $producto->tipoUnidad?->descripcion ?? 'unidad';
    $stockTotal  = (float) $stocks->sum('stock');
    $agotado     = ! $producto->es_servicio && $stockTotal <= 0;
    $bajo        = ! $producto->es_servicio && $stocks->contains(fn ($s) => $s->stock_minimo > 0 && $s->stock <= $s->stock_minimo);
    $margen      = $producto->margen();
@endphp

@section('title', $producto->nombre)

@section('content')
    <x-page-header :title="$producto->nombre" :migas="['Productos' => route('productos.index'), $producto->nombre => null]"
        :subtitle="collect([$producto->codigo ? 'Código ' . $producto->codigo : null, $producto->categoria?->nombre, $producto->es_servicio ? 'Servicio' : 'Unidad base: ' . $unidad])->filter()->implode(' · ')">
        <x-slot:badge>
            <x-badge :tone="$producto->activo ? 'success' : 'neutral'" :dot="true">{{ $producto->activo ? 'Activo' : 'Inactivo' }}</x-badge>
            @if ($agotado)
                <x-badge tone="danger" shape="box">Agotado</x-badge>
            @elseif ($bajo)
                <x-badge tone="warning" shape="box">Stock bajo</x-badge>
            @endif
        </x-slot:badge>
        <x-slot:acciones>
            @if (! $producto->es_servicio)
                @can('gestionar-compras')
                    <a href="{{ route('compras.create') }}" class="btn btn-secondary"><x-icon name="truck" class="text-brand-600" /> Reabastecer</a>
                @endcan
            @endif
            @can('update', $producto)
                <button type="button" data-eliminar="{{ route('productos.destroy', $producto) }}" data-nombre="{{ $producto->nombre }}" class="btn btn-secondary text-red-600 hover:bg-red-50">
                    <x-icon name="trash" /> Eliminar
                </button>
                <a href="{{ route('productos.edit', $producto) }}" class="btn btn-primary"><x-icon name="pencil" /> Editar</a>
            @endcan
        </x-slot:acciones>
    </x-page-header>

    <x-flash />

    {{-- Indicadores --}}
    <section class="mt-6 grid grid-cols-2 gap-4 xl:grid-cols-4">
        <x-stat-card title="Precio de venta" icon="tag" :value="'S/ ' . number_format($producto->precio_unitario, 2)">
            <span>por {{ $unidad }} · {{ $producto->impuesto?->nombre ?? 'Gravado IGV' }}</span>
        </x-stat-card>
        @if ($verCostos)
            <x-stat-card title="Costo y margen" icon="trending-up" :tone="$margen !== null && $margen < 0 ? 'red' : 'emerald'" :value="'S/ ' . number_format($producto->costo_unitario, 2)">
                <span>{{ $margen !== null ? 'Margen ' . $margen . '% sobre el precio' : 'Sin precio de venta' }}</span>
            </x-stat-card>
        @endif
        @unless ($producto->es_servicio)
            <x-stat-card title="Stock total" icon="warehouse" :tone="$agotado ? 'red' : ($bajo ? 'amber' : 'brand')" :value="Unidades::cantidad($stockTotal)">
                <span>{{ $unidad }} en {{ $stocks->count() }} {{ $stocks->count() === 1 ? 'almacén' : 'almacenes' }}</span>
            </x-stat-card>
        @endunless
        <x-stat-card title="Vendido en 30 días" icon="shopping-bag" tone="slate" :value="Unidades::cantidad($ventas30->cantidad)">
            <span>{{ $unidad }} · <x-money :value="$ventas30->importe" /> en {{ $ventas30->ventas }} {{ $ventas30->ventas == 1 ? 'venta' : 'ventas' }}</span>
        </x-stat-card>
    </section>

    <div class="mt-5 grid gap-5 lg:grid-cols-[minmax(0,1fr)_21rem] xl:grid-cols-[minmax(0,1fr)_24rem]">
        <div class="space-y-5">
            {{-- Stock por almacén --}}
            @unless ($producto->es_servicio)
                <x-card title="Stock por almacén" icon="warehouse" subtitle="En {{ $unidad }}">
                    @if ($stocks->isEmpty())
                        <p class="text-sm text-slate-500">Aún no tiene stock registrado en ningún almacén.</p>
                    @else
                        <ul class="divide-y divide-slate-100">
                            @foreach ($stocks as $s)
                                @php
                                    $pct = $s->stock_minimo > 0 ? min(100, round($s->stock / ($s->stock_minimo * 2) * 100)) : 100;
                                    $critico = $s->stock_minimo > 0 && $s->stock <= $s->stock_minimo;
                                @endphp
                                <li class="py-3">
                                    <div class="flex items-center justify-between gap-3 text-sm">
                                        <span class="font-medium">{{ $s->almacen?->nombre }} @if ($s->almacen?->es_principal)<x-badge tone="info" class="ml-1">Principal</x-badge>@endif</span>
                                        <span @class(['font-semibold', 'text-red-600' => $s->stock <= 0, 'text-amber-700' => $critico && $s->stock > 0])>{{ Unidades::cantidad($s->stock) }}</span>
                                    </div>
                                    <div class="mt-2 h-1.5 overflow-hidden rounded-full bg-slate-100">
                                        <div @class(['h-full rounded-full', 'bg-red-500' => $s->stock <= 0, 'bg-amber-500' => $critico && $s->stock > 0, 'bg-brand-500' => ! $critico]) style="width: {{ max($pct, 2) }}%"></div>
                                    </div>
                                    <p class="mt-1 text-[11px] text-slate-500">Mínimo: {{ Unidades::cantidad($s->stock_minimo) }}</p>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </x-card>
            @endunless

            {{-- Kardex --}}
            <x-card title="Últimos movimientos" icon="layers" subtitle="Kardex: entradas y salidas de stock">
                @if ($movimientos->isEmpty())
                    <x-empty-state icon="layers" title="Sin movimientos" description="Las compras, ventas y ajustes aparecerán aquí." class="py-6" />
                @else
                    <div class="-mx-4 overflow-x-auto sm:-mx-5">
                        <table class="w-full text-sm">
                            <thead class="border-y border-slate-200 bg-slate-50/70 text-left text-xs font-medium text-slate-500">
                                <tr>
                                    <th class="py-2.5 pl-4 pr-3 sm:pl-5">Fecha</th>
                                    <th class="px-3 py-2.5">Documento</th>
                                    <th class="px-3 py-2.5">Almacén</th>
                                    <th class="px-3 py-2.5 text-right">Cantidad</th>
                                    <th class="py-2.5 pl-3 pr-4 text-right sm:pr-5">Saldo</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                @foreach ($movimientos as $m)
                                    @php $entra = $m->tipo_movimiento === 'ingreso'; @endphp
                                    <tr>
                                        <td class="whitespace-nowrap py-2.5 pl-4 pr-3 text-slate-600 sm:pl-5">{{ $m->fecha_movimiento?->format('d/m/Y H:i') }}</td>
                                        <td class="px-3 py-2.5">
                                            {{ $m->referencia_label }}
                                            @if ($m->lote)<p class="text-[11px] text-slate-500">Lote {{ $m->lote->codigo_lote }}</p>@endif
                                        </td>
                                        <td class="px-3 py-2.5 text-slate-600">{{ $m->almacen?->nombre }}</td>
                                        <td @class(['px-3 py-2.5 text-right font-semibold', 'text-emerald-600' => $entra, 'text-red-600' => ! $entra])>{{ $entra ? '+' : '−' }}{{ Unidades::cantidad($m->cantidad) }}</td>
                                        <td class="py-2.5 pl-3 pr-4 text-right sm:pr-5">{{ Unidades::cantidad($m->stock_nuevo) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <a href="{{ route('kardex.index', ['producto_id' => $producto->id]) }}" class="mt-1 flex items-center justify-between border-t border-slate-200 pt-3 text-xs font-semibold text-brand-600 hover:text-brand-700">
                        Ver kardex completo <x-icon name="arrow-right" class="size-3.5" />
                    </a>
                @endif
            </x-card>

            {{-- Lotes --}}
            @if ($producto->maneja_lotes)
                <x-card title="Lotes" icon="calendar-clock" subtitle="Ordenados por fecha de vencimiento">
                    @forelse ($lotes as $l)
                        @php $dias = $l->fecha_vencimiento ? (int) today()->diffInDays($l->fecha_vencimiento, false) : null; @endphp
                        <div class="flex items-center justify-between gap-3 border-b border-slate-100 py-2.5 text-sm last:border-0">
                            <div>
                                <p class="font-medium">{{ $l->codigo_lote }}</p>
                                <p class="text-[11px] text-slate-500">Vence {{ $l->fecha_vencimiento?->format('d/m/Y') ?? '—' }}</p>
                            </div>
                            <div class="text-right">
                                <p class="font-semibold">{{ Unidades::cantidad($l->cantidad) }}</p>
                                @if ($dias !== null && $dias < 0)
                                    <x-badge tone="danger" shape="box">Vencido</x-badge>
                                @elseif ($dias !== null && $dias <= 30)
                                    <x-badge tone="warning" shape="box">{{ $dias }} días</x-badge>
                                @endif
                            </div>
                        </div>
                    @empty
                        <p class="text-sm text-slate-500">Sin lotes registrados. Se crean al comprar indicando el lote.</p>
                    @endforelse
                </x-card>
            @endif
        </div>

        <aside class="space-y-5">
            {{-- Presentaciones --}}
            @unless ($producto->es_servicio)
                <x-card title="Presentaciones" icon="ruler">
                    <ul class="divide-y divide-slate-100 text-sm">
                        <li class="flex items-center justify-between gap-3 py-2.5">
                            <span><span class="font-medium">{{ $unidad }}</span> <span class="text-xs text-slate-500">(unidad base)</span></span>
                            <span class="font-semibold"><x-money :value="$producto->precio_unitario" /></span>
                        </li>
                        @foreach ($presentaciones as $pr)
                            <li class="flex items-center justify-between gap-3 py-2.5">
                                <span>
                                    <span class="font-medium">{{ $pr->unidad }}</span>
                                    <span class="block text-xs text-slate-500">= {{ Unidades::conUnidad($pr->factor, $unidad) }}</span>
                                </span>
                                <span class="text-right">
                                    <span class="font-semibold"><x-money :value="$pr->precio" /></span>
                                    <span class="block text-[11px] text-slate-500">{{ $pr->precio_propio ? 'Precio propio' : 'Automático' }}</span>
                                </span>
                            </li>
                        @endforeach
                    </ul>
                </x-card>
            @endunless

            {{-- Fraccionamiento: en qué se parte este producto y de cuál se obtiene --}}
            @unless ($producto->es_servicio)
                <x-card title="Fraccionamiento" icon="layers" subtitle="Partir un producto en otro (1 saco → 15 kg)">
                    @if ($producto->fraccionaEn->isNotEmpty())
                        <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Se parte en</p>
                        <ul class="mt-1 divide-y divide-slate-100 text-sm">
                            @foreach ($producto->fraccionaEn as $r)
                                <li @class(['flex items-center justify-between gap-2 py-2', 'opacity-50' => ! $r->activo])>
                                    <span class="min-w-0">
                                        1 {{ $unidad }} = <strong>{{ Unidades::conUnidad($r->factor, $r->productoDestino?->tipoUnidad?->descripcion) }}</strong>
                                        <a href="{{ route('productos.show', $r->producto_destino_id) }}" class="block truncate text-xs text-brand-600 hover:text-brand-700">{{ $r->productoDestino?->nombre }}</a>
                                    </span>
                                    <span class="flex shrink-0 items-center gap-1">
                                        @if ($r->activo)
                                            @can('gestionar-inventario')
                                                <a href="{{ route('fraccionamientos.create', ['regla' => $r->id]) }}" class="rounded-md px-2 py-1 text-xs font-semibold text-brand-600 hover:bg-brand-50">Partir</a>
                                            @endcan
                                        @else
                                            <x-badge>Inactiva</x-badge>
                                        @endif
                                        @can('update', $producto)
                                            <form method="POST" action="{{ route('productos.fracciones.destroy', [$producto, $r]) }}" onsubmit="return confirm('¿Quitar esta regla?')">
                                                @csrf @method('DELETE')
                                                <button type="submit" aria-label="Quitar regla" class="grid size-7 place-items-center rounded text-slate-400 hover:bg-red-50 hover:text-red-600"><x-icon name="x" class="size-3.5" /></button>
                                            </form>
                                        @endcan
                                    </span>
                                </li>
                            @endforeach
                        </ul>
                    @endif

                    @if ($producto->seObtieneDe->isNotEmpty())
                        <p class="mt-3 text-xs font-semibold uppercase tracking-wide text-slate-400">Se obtiene de</p>
                        <ul class="mt-1 divide-y divide-slate-100 text-sm">
                            @foreach ($producto->seObtieneDe as $r)
                                <li class="py-2">
                                    <a href="{{ route('productos.show', $r->producto_id) }}" class="font-medium hover:text-brand-600">{{ $r->producto?->nombre }}</a>
                                    <span class="block text-xs text-slate-500">1 {{ $r->producto?->tipoUnidad?->descripcion }} = {{ Unidades::conUnidad($r->factor, $unidad) }}{{ config('inventario.fraccionar_al_vender') ? ' · se abre solo al vender si falta stock' : '' }}</span>
                                </li>
                            @endforeach
                        </ul>
                    @endif

                    @if ($producto->fraccionaEn->isEmpty() && $producto->seObtieneDe->isEmpty())
                        <p class="text-sm text-slate-500">Este producto no se parte ni se obtiene de otro.</p>
                    @endif

                    @can('update', $producto)
                        <form method="POST" action="{{ route('productos.fracciones.store', $producto) }}" class="mt-4 space-y-3 border-t border-slate-100 pt-4">
                            @csrf
                            <p class="text-xs font-medium text-slate-600">Nueva regla: 1 {{ $unidad }} de este producto equivale a…</p>
                            <div class="grid grid-cols-[6rem_minmax(0,1fr)] gap-2">
                                <input type="number" name="factor" step="0.0001" min="0.0001" required placeholder="15" aria-label="Cantidad equivalente" class="field">
                                <select name="producto_destino_id" required aria-label="Producto de destino" class="field">
                                    <option value="">Producto destino…</option>
                                    @foreach ($destinos as $d)
                                        <option value="{{ $d->id }}">{{ $d->nombre }} ({{ $d->tipoUnidad?->descripcion }})</option>
                                    @endforeach
                                </select>
                            </div>
                            <button type="submit" class="btn btn-secondary w-full"><x-icon name="plus" class="text-brand-600" /> Agregar regla</button>
                        </form>
                    @endcan
                </x-card>
            @endunless

            {{-- Datos --}}
            <x-card title="Datos del producto" icon="info">
                <dl class="divide-y divide-slate-100">
                    <x-dato label="Categoría">{{ $producto->categoria?->nombre ?? 'Sin categoría' }}</x-dato>
                    <x-dato label="Tipo">{{ $producto->es_servicio ? 'Servicio' : 'Producto' }}</x-dato>
                    <x-dato label="Afectación IGV">{{ $producto->impuesto?->nombre ?? 'Gravado (' . rtrim(rtrim(number_format($empresa->igv, 2), '0'), '.') . '%)' }}</x-dato>
                    @unless ($producto->es_servicio)
                        <x-dato label="Control de lotes">{{ $producto->maneja_lotes ? 'Sí' : 'No' }}</x-dato>
                    @endunless
                    <x-dato label="Creado">{{ $producto->created_at?->format('d/m/Y H:i') }}</x-dato>
                    <x-dato label="Actualizado">{{ $producto->updated_at?->diffForHumans() }}</x-dato>
                </dl>
                @if ($producto->descripcion)
                    <p class="mt-3 border-t border-slate-100 pt-3 text-sm leading-relaxed text-slate-600">{{ $producto->descripcion }}</p>
                @endif
            </x-card>

            {{-- Última compra --}}
            @if ($verCostos && ! $producto->es_servicio)
                <x-card title="Última compra" icon="truck">
                    @if ($ultimaCompra)
                        <dl class="divide-y divide-slate-100">
                            <x-dato label="Proveedor">{{ $ultimaCompra->compra?->proveedor?->nombre }}</x-dato>
                            <x-dato label="Fecha">{{ $ultimaCompra->compra?->fecha_emision?->format('d/m/Y') }}</x-dato>
                            <x-dato label="Cantidad">{{ Unidades::cantidad($ultimaCompra->cantidad) }}</x-dato>
                            <x-dato label="Costo unitario"><x-money :value="$ultimaCompra->precio_unitario" /></x-dato>
                        </dl>
                        <a href="{{ route('compras.show', $ultimaCompra->compra_id) }}" class="mt-1 flex items-center justify-between border-t border-slate-200 pt-3 text-xs font-semibold text-brand-600 hover:text-brand-700">
                            Ver compra {{ $ultimaCompra->compra?->numero_completo }} <x-icon name="arrow-right" class="size-3.5" />
                        </a>
                    @else
                        <p class="text-sm text-slate-500">Aún no se ha comprado este producto.</p>
                    @endif
                </x-card>
            @endif
        </aside>
    </div>

    @can('delete', $producto)
        <x-dialogo-eliminar titulo="Eliminar producto" mensaje="Solo se pueden eliminar productos sin ventas, compras ni movimientos; si los tiene, desactívalo desde Editar." />
    @endcan
@endsection
