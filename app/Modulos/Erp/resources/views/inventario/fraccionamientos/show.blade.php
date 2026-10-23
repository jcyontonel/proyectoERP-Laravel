{{-- FraccionamientoController@show: $f (productoOrigen, productoDestino, almacen, usuario, lotes, venta) --}}
@extends('layouts.app')

@use('App\Modulos\Catalogo\Support\Unidades')

@section('title', 'Fraccionamiento ' . $f->numero_completo)

@section('content')
    <x-page-header :title="'Fraccionamiento ' . $f->numero_completo" :migas="['Inventario' => null, 'Fraccionamientos' => route('fraccionamientos.index'), $f->numero_completo => null]"
        :subtitle="$f->fecha_movimiento->format('d/m/Y H:i') . ' · ' . $f->almacen?->nombre . ' · ' . ($f->origen === 'venta' ? 'Automático al vender' : 'Manual, por ' . $f->usuario?->nombre_completo)">
        <x-slot:badge><x-estado-inventario :estado="$f->esAnulado() ? 'anulado' : 'confirmado'" /></x-slot:badge>
        <x-slot:acciones>
            @unless ($f->esAnulado())
                <form method="POST" action="{{ route('fraccionamientos.anular', $f) }}" onsubmit="return confirm('¿Anular? Lo partido volverá al producto de origen (si aún no se vendió).')">
                    @csrf @method('PATCH')
                    <button type="submit" class="btn btn-secondary text-red-600 hover:bg-red-50"><x-icon name="x-circle" /> Anular</button>
                </form>
            @endunless
        </x-slot:acciones>
    </x-page-header>

    <x-flash />

    <section class="mt-6 grid items-stretch gap-4 md:grid-cols-[1fr_auto_1fr]">
        <div class="rounded-xl border border-red-100 bg-white p-5 shadow-sm">
            <p class="text-xs font-semibold uppercase tracking-wide text-red-500">Salió</p>
            <p class="mt-2 text-2xl font-bold">{{ Unidades::conUnidad($f->cantidad_origen, $f->productoOrigen?->tipoUnidad?->descripcion) }}</p>
            <a href="{{ route('productos.show', $f->producto_origen_id) }}" class="mt-1 block font-medium hover:text-brand-600">{{ $f->productoOrigen?->nombre }}</a>
            <p class="mt-2 text-xs text-slate-500">Costo unitario <x-money :value="$f->costo_origen" />{{ $f->loteOrigen ? ' · Lote ' . $f->loteOrigen->codigo_lote : '' }}</p>
        </div>
        <div class="grid place-items-center text-slate-400"><x-icon name="arrow-right" class="size-6 rotate-90 md:rotate-0" /></div>
        <div class="rounded-xl border border-emerald-100 bg-white p-5 shadow-sm">
            <p class="text-xs font-semibold uppercase tracking-wide text-emerald-600">Entró</p>
            <p class="mt-2 text-2xl font-bold">{{ Unidades::conUnidad($f->cantidad_destino, $f->productoDestino?->tipoUnidad?->descripcion) }}</p>
            <a href="{{ route('productos.show', $f->producto_destino_id) }}" class="mt-1 block font-medium hover:text-brand-600">{{ $f->productoDestino?->nombre }}</a>
            <p class="mt-2 text-xs text-slate-500">Costo unitario <x-money :value="$f->costo_destino" />{{ $f->loteDestino ? ' · Lote ' . $f->loteDestino->codigo_lote : '' }}</p>
        </div>
    </section>

    <x-card title="Detalle" icon="info" class="mt-5">
        <dl class="grid gap-x-8 text-sm sm:grid-cols-2">
            <x-dato label="Equivalencia">1 {{ $f->productoOrigen?->tipoUnidad?->descripcion }} = {{ Unidades::cantidad($f->factor) }} {{ $f->productoDestino?->tipoUnidad?->descripcion }}</x-dato>
            <x-dato label="Valor movido"><x-money :value="$f->cantidad_origen * $f->costo_origen" /></x-dato>
            @if ($f->venta)
                <x-dato label="Venta que lo originó"><a href="{{ route('ventas.show', $f->venta_id) }}" class="text-brand-600 hover:text-brand-700">{{ $f->venta->numero_completo }}</a></x-dato>
            @endif
            <x-dato label="Kardex"><a href="{{ route('kardex.index', ['tipo' => 'fraccionamiento', 'q' => \App\Modulos\Erp\Models\Correlativo::formatear($f->numero)]) }}" class="text-brand-600 hover:text-brand-700">Ver movimientos</a></x-dato>
        </dl>
        @if ($f->observacion)
            <p class="mt-3 border-t border-slate-100 pt-3 text-sm text-slate-600">{{ $f->observacion }}</p>
        @endif
    </x-card>
@endsection
