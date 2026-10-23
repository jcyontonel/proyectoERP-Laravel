{{-- CotizacionController@create/edit: $empresa, $clientes [id,nombre,doc], $productos [id,nombre,codigo,precio,unidad], $cotizacion (null al crear) --}}
@extends('layouts.app')

@php
    $editando = $cotizacion !== null;
    $titulo = $editando ? 'Editar ' . $cotizacion->numero_completo : 'Nueva cotización';
    $iniciales = old('productos', $editando
        ? $cotizacion->detalles->map(fn ($d) => ['id' => $d->producto_id, 'cantidad' => (float) $d->cantidad])->all()
        : []);
    $diasValidez = (int) config('ventas.cotizacion_dias_validez', 15);
@endphp

@section('title', $titulo)

@section('content')
    <x-page-header :title="$titulo" :migas="['Cotizaciones' => route('cotizaciones.index'), $titulo => null]"
        subtitle="Los precios salen del catálogo y el IGV se calcula igual que en la venta." />

    <x-flash />

    <form method="POST" action="{{ $editando ? route('cotizaciones.update', $cotizacion) : route('cotizaciones.store') }}"
        class="mt-6 grid gap-5 lg:grid-cols-[minmax(0,1fr)_22rem]">
        @csrf
        @if ($editando) @method('PUT') @endif

        <div class="space-y-5">
            <x-card title="Cliente y validez" icon="user">
                <div class="grid gap-4 sm:grid-cols-3">
                    <div class="sm:col-span-3">
                        <x-select name="cliente_id" label="Cliente (opcional)">
                            <option value="">Sin cliente / a quien corresponda</option>
                            @foreach ($clientes as $c)
                                <option value="{{ $c['id'] }}" @selected((string) old('cliente_id', $cotizacion?->cliente_id) === (string) $c['id'])>{{ $c['nombre'] }}{{ $c['doc'] ? ' · ' . $c['doc'] : '' }}</option>
                            @endforeach
                        </x-select>
                    </div>
                    <x-input name="fecha_emision" label="Emisión" type="date" :value="($cotizacion?->fecha_emision ?? now())->format('Y-m-d')" required />
                    <x-input name="vence_el" label="Válida hasta" type="date" :value="$cotizacion?->vence_el?->format('Y-m-d')" :hint="'Vacío: ' . $diasValidez . ' días desde la emisión.'" />
                    <x-input name="descuento" label="Descuento global (%)" type="number" step="0.01" min="0" max="100" :value="$cotizacion?->descuento_porcentaje ?? 0" />
                </div>
            </x-card>

            @include('partials.editor-lineas', [
                'modo' => 'precio', 'productos' => $productos, 'iniciales' => $iniciales,
                'igv' => (float) $empresa->igv, 'incluyeIgv' => (bool) $empresa->precios_incluyen_igv,
            ])

            <x-card title="Condiciones" icon="file-text">
                <div class="grid gap-4">
                    <x-textarea name="condiciones" label="Condiciones comerciales (se imprimen)" :value="$cotizacion?->condiciones" rows="3" placeholder="Forma de pago, plazo de entrega, garantía..." />
                    <x-textarea name="observacion" label="Nota interna (no se imprime)" :value="$cotizacion?->observacion" rows="2" />
                </div>
            </x-card>
        </div>

        <aside class="lg:sticky lg:top-6 lg:self-start">
            <x-card title="Resumen" icon="coins">
                <dl class="space-y-2 text-sm">
                    <div class="flex justify-between"><dt class="text-slate-600">Importe bruto</dt><dd id="t-bruto">S/ 0.00</dd></div>
                    <div class="flex justify-between"><dt class="text-slate-600">Descuento</dt><dd id="t-descuento">S/ 0.00</dd></div>
                    <div class="flex justify-between"><dt class="text-slate-600">Base imponible</dt><dd id="t-base">S/ 0.00</dd></div>
                    <div class="flex justify-between"><dt class="text-slate-600">IGV ({{ rtrim(rtrim(number_format($empresa->igv, 2), '0'), '.') }} %)</dt><dd id="t-igv">S/ 0.00</dd></div>
                    <div class="flex justify-between border-t border-slate-200 pt-2"><dt class="font-semibold">Total</dt><dd id="t-total" class="text-lg font-bold">S/ 0.00</dd></div>
                </dl>
                <button type="submit" class="btn btn-primary mt-5 w-full justify-center"><x-icon name="check-circle" /> {{ $editando ? 'Guardar cambios' : 'Emitir cotización' }}</button>
                <a href="{{ $editando ? route('cotizaciones.show', $cotizacion) : route('cotizaciones.index') }}" class="btn btn-secondary mt-2 w-full justify-center">Cancelar</a>
            </x-card>
        </aside>
    </form>
@endsection
