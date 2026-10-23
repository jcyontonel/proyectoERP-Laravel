@extends('layouts.app')

@php $editando = $categoria->exists; @endphp

@section('title', $editando ? 'Editar categoría' : 'Nueva categoría')

@section('content')
    <x-page-header :title="$editando ? 'Editar categoría' : 'Nueva categoría'" :migas="['Inventario' => null, 'Categorías' => route('categorias.index'), ($editando ? $categoria->nombre : 'Nueva') => null]" />

    <x-flash />

    <form method="POST" action="{{ $editando ? route('categorias.update', $categoria) : route('categorias.store') }}" class="mt-6 max-w-2xl">
        @csrf
        @if ($editando) @method('PUT') @endif
        <x-card title="Datos de la categoría" icon="tag">
            <div class="space-y-4">
                <x-input name="nombre" label="Nombre *" :value="$categoria->nombre" required maxlength="255" autofocus />
                <x-textarea name="descripcion" label="Descripción" :value="$categoria->descripcion" rows="3" maxlength="1000" />
            </div>
            <div class="mt-5 flex justify-end gap-2 border-t border-slate-100 pt-4">
                <a href="{{ route('categorias.index') }}" class="btn btn-secondary">Cancelar</a>
                <button type="submit" class="btn btn-primary"><x-icon name="save" /> Guardar</button>
            </div>
        </x-card>
    </form>
@endsection
