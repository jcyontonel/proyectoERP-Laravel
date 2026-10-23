{{-- CotizacionController@show: $cotizacion (cliente, detalles, usuario, venta) --}}
@extends('layouts.app')

@php
    $c = $cotizacion;
    $efectivo = $c->estadoEfectivo();
    [$etiqueta, $tono] = \App\Models\Cotizacion::ESTADOS[$efectivo];
    $acciones = [
        'aceptada'  => $c->estado === 'emitida',
        'rechazada' => in_array($c->estado, ['emitida', 'aceptada'], true),
        'emitida'   => $c->estado === 'rechazada',
        'anulada'   => in_array($c->estado, ['emitida', 'aceptada', 'rechazada'], true),
    ];
@endphp

@section('title', 'Cotización ' . $c->numero_completo)

@section('content')
    <x-page-header :title="$c->numero_completo" :migas="['Cotizaciones' => route('cotizaciones.index'), $c->numero_completo => null]"
        :subtitle="'Emitida el ' . $c->fecha_emision->translatedFormat('d M Y') . ' · válida hasta el ' . $c->vence_el->translatedFormat('d M Y') . ($c->usuario ? ' · por ' . $c->usuario->name : '')">
        <x-slot:badge><x-badge :tone="$tono" dot>{{ $etiqueta }}</x-badge></x-slot:badge>
        <x-slot:acciones>
            <a href="{{ route('cotizaciones.imprimir', $c) }}" target="_blank" rel="noopener" class="btn btn-secondary"><x-icon name="printer" class="text-brand-600" /> Imprimir</a>
            @if ($c->esEditable())
                <a href="{{ route('cotizaciones.edit', $c) }}" class="btn btn-secondary"><x-icon name="pencil" class="text-brand-600" /> Editar</a>
            @endif
            @if ($c->esConvertible())
                <form method="POST" action="{{ route('cotizaciones.convertir', $c) }}">
                    @csrf
                    <button type="submit" class="btn btn-primary"><x-icon name="receipt" /> Convertir en venta</button>
                </form>
            @endif
        </x-slot:acciones>
    </x-page-header>

    <x-flash />

    @if ($efectivo === 'vencida')
        <x-alert tone="warning" class="mt-4">La validez de esta cotización ya pasó. Aún puedes convertirla: la venta usará los precios vigentes.</x-alert>
    @endif
    @if ($c->venta)
        <x-alert tone="success" class="mt-4">Se convirtió en la venta <a href="{{ route('ventas.show', $c->venta) }}" class="font-semibold underline">{{ $c->venta->numero_completo }}</a>.</x-alert>
    @endif

    <div class="mt-6 grid gap-5 lg:grid-cols-[minmax(0,1fr)_22rem]">
        <x-card title="Detalle" icon="package">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="text-left text-xs text-slate-500">
                        <tr><th class="py-2 pr-3">Descripción</th><th class="px-3 py-2 text-right">Cant.</th><th class="px-3 py-2 text-right">Precio</th><th class="px-3 py-2 text-right">Desc.</th><th class="py-2 pl-3 text-right">Total</th></tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($c->detalles as $d)
                            <tr>
                                <td class="py-2 pr-3">{{ $d->descripcion }}<span class="block text-xs text-slate-500">{{ $d->tipoUnidad?->descripcion }}</span></td>
                                <td class="px-3 py-2 text-right">{{ rtrim(rtrim(number_format($d->cantidad, 4, '.', ''), '0'), '.') }}</td>
                                <td class="px-3 py-2 text-right"><x-money :value="$d->precio_unitario" /></td>
                                <td class="px-3 py-2 text-right">{{ $d->descuento > 0 ? '− ' . number_format($d->descuento, 2) : '—' }}</td>
                                <td class="py-2 pl-3 text-right font-medium"><x-money :value="$d->total" /></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @if ($c->condiciones)
                <div class="mt-4 rounded-lg bg-slate-50 px-3 py-2 text-sm">
                    <p class="text-xs font-semibold text-slate-500">Condiciones</p>
                    <p class="mt-1 whitespace-pre-line">{{ $c->condiciones }}</p>
                </div>
            @endif
        </x-card>

        <aside class="space-y-5">
            <x-card title="Importes" icon="coins">
                <dl class="space-y-2 text-sm">
                    @if ($c->descuento > 0)
                        <div class="flex justify-between"><dt class="text-slate-600">Descuento ({{ rtrim(rtrim(number_format($c->descuento_porcentaje, 2), '0'), '.') }} %)</dt><dd>− <x-money :value="$c->descuento" /></dd></div>
                    @endif
                    <div class="flex justify-between"><dt class="text-slate-600">Base imponible</dt><dd><x-money :value="$c->subtotal" /></dd></div>
                    <div class="flex justify-between"><dt class="text-slate-600">IGV</dt><dd><x-money :value="$c->total_impuestos" /></dd></div>
                    <div class="flex justify-between border-t border-slate-200 pt-2"><dt class="font-semibold">Total</dt><dd @class(['text-lg font-bold', 'line-through opacity-60' => $c->estado === 'anulada'])><x-money :value="$c->total" /></dd></div>
                </dl>
            </x-card>
            <x-card title="Cliente" icon="user">
                <p class="font-medium">{{ $c->cliente?->nombre_completo ?? 'Sin cliente' }}</p>
                <p class="text-xs text-slate-500">{{ $c->cliente?->documento_etiqueta }}</p>
            </x-card>
            @if (in_array(true, $acciones, true))
                <x-card title="Seguimiento" icon="history">
                    <div class="flex flex-wrap gap-2">
                        @foreach (['aceptada' => ['Aceptada por el cliente', 'check-circle', ''], 'rechazada' => ['Rechazada', 'x-circle', ''], 'emitida' => ['Reabrir', 'history', ''], 'anulada' => ['Anular', 'x-circle', 'text-red-600 hover:bg-red-50']] as $estado => [$texto, $icono, $clase])
                            @if ($acciones[$estado])
                                <form method="POST" action="{{ route('cotizaciones.estado', $c) }}">
                                    @csrf @method('PATCH')
                                    <input type="hidden" name="estado" value="{{ $estado }}">
                                    <button type="submit" class="btn btn-secondary {{ $clase }}"><x-icon :name="$icono" /> {{ $texto }}</button>
                                </form>
                            @endif
                        @endforeach
                    </div>
                </x-card>
            @endif
            @if ($c->observacion)
                <x-card title="Nota interna" icon="info">
                    <p class="whitespace-pre-line text-sm text-slate-600">{{ $c->observacion }}</p>
                </x-card>
            @endif
        </aside>
    </div>
@endsection
