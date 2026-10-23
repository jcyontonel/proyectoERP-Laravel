{{--
    Variables (CompraController@index):
      $compras      LengthAwarePaginator de Compra (con proveedor, almacen y el atributo `pagado`)
      $pendiente    float  saldo por pagar de lo filtrado
      $proveedores  colección para el filtro
    Filtros (GET): q, proveedor_id, estado, pago, desde, hasta
--}}
@extends('layouts.app')

@section('title', 'Compras')

@section('content')
    <nav aria-label="Ruta" class="flex items-center gap-2 text-xs text-slate-500">
        <a href="{{ route('inicio') }}" class="hover:text-brand-600">Inicio</a>
        <x-icon name="chevron-right" class="size-3" />
        <span class="text-ink">Compras</span>
    </nav>

    <div class="mt-4 flex flex-wrap items-start justify-between gap-x-6 gap-y-3">
        <div class="min-w-0">
            <h1 class="text-2xl font-bold tracking-tight sm:text-3xl">Compras</h1>
            <p class="mt-1.5 max-w-lg text-sm text-slate-500">Registra las compras a tus proveedores. Cada compra ingresa stock al almacén y deja su movimiento en el kardex.</p>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            <a href="{{ route('proveedores.index') }}" class="btn btn-secondary"><x-icon name="building" class="text-brand-600" /> Proveedores</a>
            <a href="{{ route('compras.create') }}" class="btn btn-primary"><x-icon name="plus" /> Nueva compra</a>
        </div>
    </div>

    @foreach (['status' => 'success', 'error' => 'danger'] as $clave => $tono)
        @if (session($clave))
            <x-alert :tone="$tono" class="mt-4">{{ session($clave) }}</x-alert>
        @endif
    @endforeach
    @if ($errors->any())
        <x-alert tone="danger" class="mt-4">{{ $errors->first() }}</x-alert>
    @endif

    @include('compras.partials.filtros')
    @include('compras.partials.listado')
    @include('compras.partials.dialogo-anular')
@endsection
