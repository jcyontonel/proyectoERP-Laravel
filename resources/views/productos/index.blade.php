{{--
    Variables (ProductoController@index):
      $productos   paginador de Producto (con categoria, tipoUnidad, stock_total, presentaciones, stock_bajo)
      $categorias  para el filtro
      $resumen     total, servicios, bajo, agotados, valor
      $verCostos   bool (ProductoPolicy@verCostos)
    Filtros (GET): q, categoria_id, tipo, estado, stock, orden
--}}
@extends('layouts.app')

@section('title', 'Productos')

@section('content')
    <x-page-header title="Productos" :migas="['Inventario' => null, 'Productos' => null]"
        subtitle="Tu catálogo, con el stock de cada almacén y las presentaciones en que lo compras y vendes (saco, caja, kilo suelto…).">
        <x-slot:acciones>
            @can('gestionar-compras')
                <a href="{{ route('compras.create') }}" class="btn btn-secondary"><x-icon name="truck" class="text-brand-600" /> Nueva compra</a>
            @endcan
            @can('create', App\Models\Producto::class)
                <a href="{{ route('productos.create') }}" class="btn btn-primary"><x-icon name="plus" /> Nuevo producto</a>
            @endcan
        </x-slot:acciones>
    </x-page-header>

    <x-flash />

    <section aria-label="Resumen del catálogo" class="mt-6 grid grid-cols-2 gap-4 xl:grid-cols-4">
        <x-stat-card title="Productos activos" icon="package" :value="number_format($resumen['total'])">
            <span>{{ number_format($resumen['servicios']) }} {{ $resumen['servicios'] === 1 ? 'es servicio' : 'son servicios' }}</span>
        </x-stat-card>
        <x-stat-card title="Stock bajo" icon="alert-triangle" tone="amber" :value="number_format($resumen['bajo'])" :href="route('productos.index', ['stock' => 'bajo'])">
            <span>en o bajo el mínimo</span>
        </x-stat-card>
        <x-stat-card title="Agotados" icon="x-circle" tone="red" :value="number_format($resumen['agotados'])" :href="route('productos.index', ['stock' => 'agotado'])">
            <span>sin stock en ningún almacén</span>
        </x-stat-card>
        @if ($verCostos)
            <x-stat-card title="Inventario valorizado" icon="pie-chart" tone="emerald" :value="'S/ ' . number_format($resumen['valor'], 2)">
                <span>stock × costo unitario</span>
            </x-stat-card>
        @else
            <x-stat-card title="Categorías" icon="tag" tone="slate" :value="number_format($categorias->count())">
                <span>familias del catálogo</span>
            </x-stat-card>
        @endif
    </section>

    @include('productos.partials.filtros')
    @include('productos.partials.listado')

    <x-dialogo-eliminar titulo="Eliminar producto" mensaje="Solo se pueden eliminar productos sin ventas, compras ni movimientos." />
@endsection
