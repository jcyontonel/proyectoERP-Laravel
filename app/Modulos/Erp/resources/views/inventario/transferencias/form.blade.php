{{-- Crear / editar transferencia: $transferencia, $almacenes, $productos (CatalogoInventario) --}}
@extends('layouts.app')

@php
    $editando = $transferencia->exists;
    $titulo = $editando ? 'Editar transferencia ' . $transferencia->numero_completo : 'Nueva transferencia';
    $iniciales = old('productos', $editando ? $transferencia->detalles->map(fn ($d) => [
        'id' => $d->producto_id, 'cantidad' => $d->cantidad_solicitada, 'lote' => $d->lote?->codigo_lote,
    ])->all() : []);
    $origen = old('almacen_origen_id', $transferencia->almacen_origen_id);
    $destino = old('almacen_destino_id', $transferencia->almacen_destino_id ?? $almacenes->firstWhere('id', '!=', $origen)?->id);
@endphp

@section('title', $titulo)

@section('content')
    <x-page-header :title="$titulo" :migas="['Inventario' => null, 'Transferencias' => route('transferencias.index'), ($editando ? $transferencia->numero_completo : 'Nueva') => null]"
        subtitle="El stock que se muestra es el del almacén de origen. Al enviar, la mercadería sale del origen y queda en tránsito hasta que el destino la reciba." />

    <x-flash />

    <form method="POST" action="{{ $editando ? route('transferencias.update', $transferencia) : route('transferencias.store') }}"
        class="mt-6 grid gap-5 lg:grid-cols-[minmax(0,1fr)_20rem]">
        @csrf
        @if ($editando) @method('PUT') @endif

        @include('inventario.partials.editor-lineas', ['iniciales' => $iniciales, 'almacenSelector' => '#almacen_origen_id', 'class' => 'lg:row-span-2'])

        <aside class="space-y-5 lg:sticky lg:top-24 lg:self-start">
            <x-card title="Datos de la transferencia" icon="arrow-left-right">
                <div class="space-y-4">
                    <x-select name="almacen_origen_id" label="Almacén de origen *">
                        @foreach ($almacenes as $a)
                            <option value="{{ $a->id }}" @selected((string) $origen === (string) $a->id)>{{ $a->nombre }}</option>
                        @endforeach
                    </x-select>
                    <x-select name="almacen_destino_id" label="Almacén de destino *">
                        @foreach ($almacenes as $a)
                            <option value="{{ $a->id }}" @selected((string) $destino === (string) $a->id)>{{ $a->nombre }}</option>
                        @endforeach
                    </x-select>
                    <x-input name="fecha_movimiento" label="Fecha *" type="date" :value="old('fecha_movimiento', $transferencia->fecha_movimiento?->format('Y-m-d'))" max="{{ now()->format('Y-m-d') }}" />
                    <x-textarea name="observacion" label="Observación" :value="$transferencia->observacion" rows="2" maxlength="1000" placeholder="Ej. Reposición semanal de la sede norte" />
                </div>
            </x-card>

            <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5">
                <button type="submit" name="accion" value="enviar" class="btn btn-primary w-full"><x-icon name="truck" /> Guardar y enviar</button>
                <button type="submit" name="accion" value="borrador" class="btn btn-secondary mt-2 w-full"><x-icon name="save" class="text-brand-600" /> Guardar borrador</button>
                <a href="{{ $editando ? route('transferencias.show', $transferencia) : route('transferencias.index') }}" class="mt-3 block text-center text-xs text-slate-500 hover:text-ink">Cancelar</a>
            </div>
        </aside>
    </form>
@endsection
