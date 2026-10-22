@extends('layouts.app')

@php $editando = $caja->exists; @endphp

@section('title', $editando ? 'Editar caja' : 'Nueva caja')

@section('content')
    <x-page-header :title="$editando ? 'Editar caja' : 'Nueva caja'" :migas="['Configuración' => null, 'Cajas' => route('cajas.index'), ($editando ? $caja->nombre : 'Nueva') => null]" />

    <x-flash />

    <form method="POST" action="{{ $editando ? route('cajas.update', $caja) : route('cajas.store') }}" class="mt-6 max-w-2xl">
        @csrf
        @if ($editando) @method('PUT') @endif
        <x-card title="Datos de la caja" icon="wallet">
            <div class="grid gap-4 sm:grid-cols-[10rem_minmax(0,1fr)]">
                <x-input name="codigo" label="Código *" :value="$caja->codigo" required maxlength="20" placeholder="CAJA-02" class="uppercase" />
                <x-input name="nombre" label="Nombre *" :value="$caja->nombre" required maxlength="100" placeholder="Caja mostrador 2" />
                <div class="sm:col-span-2">
                    <x-select name="almacen_id" label="Almacén / sede">
                        <option value="">Sin almacén</option>
                        @foreach ($almacenes as $a)
                            <option value="{{ $a->id }}" @selected((string) old('almacen_id', $caja->almacen_id) === (string) $a->id)>{{ $a->nombre }}</option>
                        @endforeach
                    </x-select>
                </div>
            </div>
            <div class="mt-5 border-t border-slate-100 pt-4">
                <x-toggle name="activo" label="Activa" :checked="$caja->activo" hint="Una caja inactiva no se puede abrir." />
            </div>
            <div class="mt-5 flex justify-end gap-2 border-t border-slate-100 pt-4">
                <a href="{{ route('cajas.index') }}" class="btn btn-secondary">Cancelar</a>
                <button type="submit" class="btn btn-primary"><x-icon name="save" /> Guardar</button>
            </div>
        </x-card>
    </form>
@endsection
