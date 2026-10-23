{{-- OrdenCompraController@show: $orden (proveedor, almacen, detalles.producto, usuario, compras) --}}
@extends('layouts.app')

@php
    $o = $orden;
    [$etiqueta, $tono] = \App\Modulos\Erp\Models\OrdenCompra::ESTADOS[$o->estado];
    $qty = fn ($n) => rtrim(rtrim(number_format($n, 4, '.', ''), '0'), '.');
@endphp

@section('title', 'Orden ' . $o->numero_completo)

@section('content')
    <x-page-header :title="$o->numero_completo" :migas="['Órdenes de compra' => route('ordenes.index'), $o->numero_completo => null]"
        :subtitle="'Emitida el ' . $o->fecha_emision->translatedFormat('d M Y') . ($o->fecha_entrega ? ' · entrega ' . $o->fecha_entrega->translatedFormat('d M Y') : '') . ($o->usuario ? ' · por ' . $o->usuario->name : '')">
        <x-slot:badge><x-badge :tone="$tono" dot>{{ $etiqueta }}</x-badge></x-slot:badge>
        <x-slot:acciones>
            <a href="{{ route('ordenes.imprimir', $o) }}" target="_blank" rel="noopener" class="btn btn-secondary"><x-icon name="printer" class="text-brand-600" /> Imprimir</a>
            @if ($o->esEditable())
                <a href="{{ route('ordenes.edit', $o) }}" class="btn btn-secondary"><x-icon name="pencil" class="text-brand-600" /> Editar</a>
            @endif
            @if ($o->esRecibible())
                <form method="POST" action="{{ route('ordenes.recibir', $o) }}">
                    @csrf
                    <button type="submit" class="btn btn-primary"><x-icon name="package" /> Recibir mercadería</button>
                </form>
            @endif
        </x-slot:acciones>
    </x-page-header>

    <x-flash />

    @if ($o->estaAtrasada())
        <x-alert tone="warning" class="mt-4">La fecha de entrega ya pasó y aún falta mercadería por recibir.</x-alert>
    @endif

    <div class="mt-6 grid gap-5 lg:grid-cols-[minmax(0,1fr)_22rem]">
        <div class="space-y-5">
            <x-card title="Detalle" icon="package">
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="text-left text-xs text-slate-500">
                            <tr><th class="py-2 pr-3">Producto</th><th class="px-3 py-2 text-right">Pedido</th><th class="px-3 py-2 text-right">Recibido</th><th class="px-3 py-2 text-right">Costo s/ IGV</th><th class="py-2 pl-3 text-right">Total</th></tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach ($o->detalles as $d)
                                <tr>
                                    <td class="py-2 pr-3">{{ $d->descripcion }}<span class="block text-xs text-slate-500">{{ $d->producto?->tipoUnidad?->descripcion }}</span></td>
                                    <td class="px-3 py-2 text-right">{{ $qty($d->cantidad) }}</td>
                                    <td @class(['px-3 py-2 text-right', 'text-emerald-700 font-medium' => $d->pendiente() <= 0, 'text-amber-700' => $d->cantidad_recibida > 0 && $d->pendiente() > 0])>{{ $qty($d->cantidad_recibida) }}</td>
                                    <td class="px-3 py-2 text-right">{{ number_format($d->costo_unitario, 2) }}</td>
                                    <td class="py-2 pl-3 text-right font-medium"><x-money :value="$d->total" /></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </x-card>

            <x-card title="Recepciones" icon="truck" subtitle="Compras registradas desde esta orden.">
                @forelse ($o->compras as $compra)
                    <div class="flex items-center justify-between border-b border-slate-100 py-2 text-sm last:border-0">
                        <a href="{{ route('compras.show', $compra) }}" class="font-medium text-brand-600 hover:underline">{{ $compra->numero_completo }}</a>
                        <span class="text-slate-500">{{ $compra->fecha_emision->format('d/m/Y') }}</span>
                        <x-money :value="$compra->total" />
                    </div>
                @empty
                    <p class="text-sm text-slate-500">Aún no se recibe mercadería. Cuando llegue, usa «Recibir mercadería»: se abrirá la compra con lo pendiente.</p>
                @endforelse
            </x-card>
        </div>

        <aside class="space-y-5">
            <x-card title="Importes" icon="coins">
                <dl class="space-y-2 text-sm">
                    <div class="flex justify-between"><dt class="text-slate-600">Subtotal</dt><dd><x-money :value="$o->subtotal" /></dd></div>
                    <div class="flex justify-between"><dt class="text-slate-600">IGV {{ $o->afecto_igv ? '' : '(no afecta)' }}</dt><dd><x-money :value="$o->total_impuestos" /></dd></div>
                    <div class="flex justify-between border-t border-slate-200 pt-2"><dt class="font-semibold">Total</dt><dd @class(['text-lg font-bold', 'line-through opacity-60' => $o->estado === 'anulada'])><x-money :value="$o->total" /></dd></div>
                </dl>
                <p class="mt-3 text-xs text-slate-500">Pago {{ $o->condicion_pago === 'credito' ? 'a crédito' : 'al contado' }} · recibe {{ $o->almacen?->nombre }}</p>
            </x-card>
            <x-card title="Proveedor" icon="building">
                <p class="font-medium">{{ $o->proveedor?->nombre }}</p>
                <p class="text-xs text-slate-500">{{ $o->proveedor?->tipo_doc_etiqueta }} {{ $o->proveedor?->numero_documento }}</p>
            </x-card>
            @if ($o->observacion)
                <x-card title="Observaciones" icon="info">
                    <p class="whitespace-pre-line text-sm text-slate-600">{{ $o->observacion }}</p>
                </x-card>
            @endif
            @if ($o->estado === 'parcial' || ($o->estado === 'emitida' && $o->compras->isEmpty()))
                <x-card title="Acciones" icon="settings">
                    @if ($o->estado === 'parcial')
                        <form method="POST" action="{{ route('ordenes.cerrar', $o) }}" onsubmit="return confirm('¿Cerrar la orden? Ya no se esperará la mercadería pendiente.')">
                            @csrf @method('PATCH')
                            <button type="submit" class="btn btn-secondary w-full justify-center"><x-icon name="check-circle" /> Cerrar (no llegará el resto)</button>
                        </form>
                    @else
                        <form method="POST" action="{{ route('ordenes.anular', $o) }}" onsubmit="return confirm('¿Anular la orden {{ $o->numero_completo }}?')">
                            @csrf @method('PATCH')
                            <button type="submit" class="btn btn-secondary w-full justify-center text-red-600 hover:bg-red-50"><x-icon name="x-circle" /> Anular orden</button>
                        </form>
                    @endif
                </x-card>
            @endif
        </aside>
    </div>
@endsection
