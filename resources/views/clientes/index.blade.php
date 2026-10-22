{{--
    Variables (ClienteController@index):
      $clientes        paginador de Cliente (tipoDocumento, ventas_count, vendido, ultima_compra)
      $tiposDocumento  para el filtro
      $resumen         total, activos, empresas (con RUC), compraron (este mes)
    Filtros (GET): q, tipo_documento_id, estado
--}}
@extends('layouts.app')

@section('title', 'Clientes')

@section('content')
    <x-page-header title="Clientes" :migas="['Ventas' => null, 'Clientes' => null]"
        subtitle="Las personas y empresas a las que vendes, con su historial de compras en tu empresa.">
        <x-slot:acciones>
            @can('create', App\Models\Cliente::class)
                <a href="{{ route('clientes.create') }}" class="btn btn-primary"><x-icon name="plus" /> Nuevo cliente</a>
            @endcan
        </x-slot:acciones>
    </x-page-header>

    <x-flash />

    <section aria-label="Resumen de la cartera" class="mt-6 grid grid-cols-2 gap-4 xl:grid-cols-4">
        <x-stat-card title="Clientes" icon="users" :value="number_format($resumen['total'])">
            <span>vinculados a tu empresa</span>
        </x-stat-card>
        <x-stat-card title="Activos" icon="check-circle" tone="emerald" :value="number_format($resumen['activos'])" :href="route('clientes.index', ['estado' => 'activos'])">
            <span>disponibles al vender</span>
        </x-stat-card>
        <x-stat-card title="Empresas (RUC)" icon="building" tone="slate" :value="number_format($resumen['empresas'])">
            <span>pueden recibir factura</span>
        </x-stat-card>
        <x-stat-card title="Compraron este mes" icon="shopping-bag" tone="amber" :value="number_format($resumen['compraron'])">
            <span>clientes con al menos una venta</span>
        </x-stat-card>
    </section>

    @include('clientes.partials.filtros')
    @include('clientes.partials.listado')

    <x-dialogo-eliminar titulo="Quitar cliente" mensaje="Se quita de tu empresa; no es posible si tiene ventas registradas." />
@endsection
