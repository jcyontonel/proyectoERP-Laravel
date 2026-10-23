{{--
    Variable (ProveedorController@index): $proveedores (paginador de Proveedor con tipoDocumento y compras_count)
    Filtros (GET): q, estado (activos|inactivos)
--}}
@extends('layouts.app')

@section('title', 'Proveedores')

@section('content')
    <nav aria-label="Ruta" class="flex items-center gap-2 text-xs text-slate-500">
        <a href="{{ route('inicio') }}" class="hover:text-brand-600">Inicio</a>
        <x-icon name="chevron-right" class="size-3" />
        <span class="text-ink">Proveedores</span>
    </nav>

    <div class="mt-4 flex flex-wrap items-start justify-between gap-x-6 gap-y-3">
        <div class="min-w-0">
            <h1 class="text-2xl font-bold tracking-tight sm:text-3xl">Proveedores</h1>
            <p class="mt-1.5 max-w-lg text-sm text-slate-500">Empresas y personas a las que le compras mercadería.</p>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            <a href="{{ route('compras.index') }}" class="btn btn-secondary"><x-icon name="truck" class="text-brand-600" /> Compras</a>
            <a href="{{ route('proveedores.create') }}" class="btn btn-primary"><x-icon name="plus" /> Nuevo proveedor</a>
        </div>
    </div>

    @foreach (['status' => 'success', 'error' => 'danger'] as $clave => $tono)
        @if (session($clave))
            <x-alert :tone="$tono" class="mt-4">{{ session($clave) }}</x-alert>
        @endif
    @endforeach

    @include('proveedores.partials.filtros')
    @include('proveedores.partials.listado')
@endsection
