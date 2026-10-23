@extends('layouts.app')

@php $editando = $almacen->exists; @endphp

@section('title', $editando ? 'Editar almacén' : 'Nuevo almacén')

@section('content')
    <x-page-header :title="$editando ? 'Editar almacén' : 'Nuevo almacén'" :migas="['Configuración' => null, 'Almacenes' => route('almacenes.index'), ($editando ? $almacen->nombre : 'Nuevo') => null]" />

    <x-flash />

    <form method="POST" action="{{ $editando ? route('almacenes.update', $almacen) : route('almacenes.store') }}" class="mt-6 max-w-2xl">
        @csrf
        @if ($editando) @method('PUT') @endif
        <x-card title="Datos del almacén" icon="warehouse">
            <div class="grid gap-4 sm:grid-cols-[10rem_minmax(0,1fr)]">
                <x-input name="codigo" label="Código *" :value="$almacen->codigo" required maxlength="20" placeholder="ALM-02" class="uppercase" />
                <x-input name="nombre" label="Nombre *" :value="$almacen->nombre" required maxlength="255" placeholder="Sede Norte" />
                <div class="sm:col-span-2"><x-input name="direccion" label="Dirección" :value="$almacen->direccion" maxlength="255" /></div>
            </div>
            <div class="mt-5 space-y-4 border-t border-slate-100 pt-4">
                <x-toggle name="es_principal" label="Almacén principal" :checked="$almacen->es_principal" hint="Se selecciona por defecto al vender y comprar. Solo puede haber uno." />
                <x-toggle name="activo" label="Activo" :checked="$almacen->activo" hint="Un almacén inactivo no aparece al vender, comprar ni transferir." />
            </div>
            <div class="mt-5 flex justify-end gap-2 border-t border-slate-100 pt-4">
                <a href="{{ route('almacenes.index') }}" class="btn btn-secondary">Cancelar</a>
                <button type="submit" class="btn btn-primary"><x-icon name="save" /> Guardar</button>
            </div>
        </x-card>
    </form>
@endsection
