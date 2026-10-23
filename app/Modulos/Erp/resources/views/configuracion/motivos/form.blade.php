@extends('layouts.app')

@php $editando = $motivo->exists; @endphp

@section('title', $editando ? 'Editar motivo' : 'Nuevo motivo')

@section('content')
    <x-page-header :title="$editando ? 'Editar motivo de ajuste' : 'Nuevo motivo de ajuste'" :migas="['Configuración' => null, 'Motivos de ajuste' => route('motivos.index'), ($editando ? $motivo->nombre : 'Nuevo') => null]" />

    <x-flash />

    <form method="POST" action="{{ $editando ? route('motivos.update', $motivo) : route('motivos.store') }}" class="mt-6 max-w-2xl">
        @csrf
        @if ($editando) @method('PUT') @endif
        <x-card title="Datos del motivo" icon="list-checks">
            <div class="grid gap-4 sm:grid-cols-[10rem_minmax(0,1fr)]">
                <x-input name="codigo" label="Código *" :value="$motivo->codigo" required maxlength="50" placeholder="ROBO" class="uppercase" />
                <x-input name="nombre" label="Nombre *" :value="$motivo->nombre" required maxlength="120" placeholder="Robo o pérdida" />
                <div class="sm:col-span-2">
                    <x-select name="tipo_movimiento" label="Se puede usar en *">
                        @foreach ($tipos as $v => $e)
                            <option value="{{ $v }}" @selected(old('tipo_movimiento', $motivo->tipo_movimiento) === $v)>{{ $e }}</option>
                        @endforeach
                    </x-select>
                </div>
            </div>
            <div class="mt-5 space-y-4 border-t border-slate-100 pt-4">
                <x-toggle name="requiere_aprobacion" label="Requiere aprobación" :checked="$motivo->requiere_aprobacion" hint="El ajuste queda pendiente hasta que un responsable lo apruebe." />
                <x-toggle name="activo" label="Activo" :checked="$motivo->activo" />
            </div>
            <div class="mt-5 flex justify-end gap-2 border-t border-slate-100 pt-4">
                <a href="{{ route('motivos.index') }}" class="btn btn-secondary">Cancelar</a>
                <button type="submit" class="btn btn-primary"><x-icon name="save" /> Guardar</button>
            </div>
        </x-card>
    </form>
@endsection
