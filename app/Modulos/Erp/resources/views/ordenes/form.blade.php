{{-- OrdenCompraController@create/edit: $empresa, $proveedores [id,nombre,doc], $almacenes, $productos [id,nombre,codigo,precio(costo),unidad], $orden (null al crear) --}}
@extends('layouts.app')

@php
    $editando = $orden !== null;
    $titulo = $editando ? 'Editar ' . $orden->numero_completo : 'Nueva orden de compra';
    $iniciales = old('productos', $editando
        ? $orden->detalles->map(fn ($d) => ['id' => $d->producto_id, 'cantidad' => (float) $d->cantidad, 'costo' => (float) $d->costo_unitario])->all()
        : []);
    $almSel = (string) old('almacen_id', $orden?->almacen_id ?? $almacenes->firstWhere('es_principal', true)?->id ?? $almacenes->first()?->id);
@endphp

@section('title', $titulo)

@section('content')
    <x-page-header :title="$titulo" :migas="['Órdenes de compra' => route('ordenes.index'), $titulo => null]"
        subtitle="La orden no mueve stock: la mercadería ingresa cuando registras la compra desde la orden." />

    <x-flash />

    <form method="POST" action="{{ $editando ? route('ordenes.update', $orden) : route('ordenes.store') }}"
        class="mt-6 grid gap-5 lg:grid-cols-[minmax(0,1fr)_22rem]">
        @csrf
        @if ($editando) @method('PUT') @endif

        <div class="space-y-5">
            <x-card title="Proveedor y entrega" icon="truck">
                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="sm:col-span-2">
                        <x-select name="proveedor_id" label="Proveedor" required>
                            <option value="">Selecciona...</option>
                            @foreach ($proveedores as $p)
                                <option value="{{ $p['id'] }}" @selected((string) old('proveedor_id', $orden?->proveedor_id) === (string) $p['id'])>{{ $p['nombre'] }}{{ $p['doc'] ? ' · ' . $p['doc'] : '' }}</option>
                            @endforeach
                        </x-select>
                    </div>
                    <x-select name="almacen_id" label="Almacén que recibe" required>
                        @foreach ($almacenes as $a)
                            <option value="{{ $a->id }}" @selected($almSel === (string) $a->id)>{{ $a->nombre }}</option>
                        @endforeach
                    </x-select>
                    <x-select name="condicion_pago" label="Condición de pago">
                        <option value="contado" @selected(old('condicion_pago', $orden?->condicion_pago ?? 'contado') === 'contado')>Contado</option>
                        <option value="credito" @selected(old('condicion_pago', $orden?->condicion_pago) === 'credito')>Crédito</option>
                    </x-select>
                    <x-input name="fecha_emision" label="Emisión" type="date" :value="($orden?->fecha_emision ?? now())->format('Y-m-d')" required />
                    <x-input name="fecha_entrega" label="Entrega esperada" type="date" :value="$orden?->fecha_entrega?->format('Y-m-d')" />
                </div>
            </x-card>

            @include('partials.editor-lineas', ['modo' => 'costo', 'productos' => $productos, 'iniciales' => $iniciales, 'igv' => (float) $empresa->igv])

            <x-card title="Observaciones" icon="file-text">
                <x-textarea name="observacion" label="Indicaciones para el proveedor (se imprimen)" :value="$orden?->observacion" rows="3" />
            </x-card>
        </div>

        <aside class="lg:sticky lg:top-6 lg:self-start">
            <x-card title="Resumen" icon="coins">
                <label class="flex items-center gap-2 text-sm">
                    <input type="hidden" name="afecto_igv" value="0">
                    <input type="checkbox" id="afecto_igv" name="afecto_igv" value="1" @checked(old('afecto_igv', $orden?->afecto_igv ?? true)) class="size-4 rounded border-slate-300 accent-brand-600">
                    Afecta a IGV ({{ rtrim(rtrim(number_format($empresa->igv, 2), '0'), '.') }} %)
                </label>
                <dl class="mt-4 space-y-2 text-sm">
                    <div class="flex justify-between"><dt class="text-slate-600">Subtotal</dt><dd id="t-base">S/ 0.00</dd></div>
                    <div class="flex justify-between"><dt class="text-slate-600">IGV</dt><dd id="t-igv">S/ 0.00</dd></div>
                    <div class="flex justify-between border-t border-slate-200 pt-2"><dt class="font-semibold">Total</dt><dd id="t-total" class="text-lg font-bold">S/ 0.00</dd></div>
                </dl>
                <button type="submit" class="btn btn-primary mt-5 w-full justify-center"><x-icon name="check-circle" /> {{ $editando ? 'Guardar cambios' : 'Emitir orden' }}</button>
                <a href="{{ $editando ? route('ordenes.show', $orden) : route('ordenes.index') }}" class="btn btn-secondary mt-2 w-full justify-center">Cancelar</a>
            </x-card>
        </aside>
    </form>
@endsection
