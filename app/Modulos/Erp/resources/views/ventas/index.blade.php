{{--
    Variables que debe enviar VentaController@index:
      $ventas  LengthAwarePaginator (->withQueryString()) de objetos/modelos con:
               id, numero, fecha (Carbon), cliente_nombre, cliente_doc, tipo (Boleta|Factura|…),
               metodo (tarjeta|yape|efectivo|null), metodo_label, total, estado (completada|borrador|anulada)
    Filtros (GET): q, tipo, estado, desde, hasta
--}}
@extends('layouts.app')

@section('title', 'Ventas')

@section('content')
    <nav aria-label="Ruta" class="flex items-center gap-2 text-xs text-slate-500">
        <a href="{{ route('inicio') }}" class="hover:text-brand-600">Inicio</a>
        <x-icon name="chevron-right" class="size-3" />
        <span class="text-ink">Ventas</span>
    </nav>

    <div class="mt-4 flex flex-wrap items-start justify-between gap-x-6 gap-y-3">
        <div class="min-w-0">
            <h1 class="text-2xl font-bold tracking-tight sm:text-3xl">Ventas</h1>
            <p class="mt-1.5 max-w-md text-sm text-slate-500">Gestiona, filtra y consulta los comprobantes emitidos en el punto de venta.</p>
        </div>
        <a href="{{ route('ventas.create') }}" class="btn btn-primary"><x-icon name="plus" /> Nueva Venta</a>
    </div>

    @if (session('status'))
        <div role="status" class="mt-4 flex items-center gap-2 rounded-lg bg-emerald-50 px-4 py-2.5 text-sm text-emerald-700">
            <x-icon name="check-circle" /> {{ session('status') }}
        </div>
    @endif

    @if (session('error') || $errors->any())
        <div role="alert" class="mt-4 flex items-center gap-2 rounded-lg bg-red-50 px-4 py-2.5 text-sm text-red-700">
            <x-icon name="alert-triangle" /> {{ session('error') ?? $errors->first() }}
        </div>
    @endif

    @include('ventas.partials.filtros')
    @include('ventas.partials.listado')
    @include('ventas.partials.dialogo-anular')
@endsection
