{{-- Accesos rápidos según permisos, módulos del plan y estado de la suscripción (@ruta) --}}
<div class="mt-5 flex flex-wrap items-center gap-3">
    @can('emitir-ventas')
        @ruta('ventas.create')
            <a href="{{ route('ventas.create') }}" class="btn btn-primary"><x-icon name="plus" /> Nueva venta</a>
        @endruta
    @endcan
    @can('gestionar-caja')
        <a href="{{ route('caja.index') }}" class="btn btn-secondary"><x-icon name="wallet" class="text-brand-600" /> {{ ($caja['abierta'] ?? false) ? 'Cerrar caja' : 'Abrir caja' }}</a>
    @endcan
    @can('create', App\Modulos\Erp\Models\Cliente::class)
        @ruta('clientes.create')
            <a href="{{ route('clientes.create') }}" class="btn btn-secondary"><x-icon name="user" class="text-brand-600" /> Registrar cliente</a>
        @endruta
    @endcan
    @can('gestionar-compras')
        @ruta('compras.create')
            <a href="{{ route('compras.create') }}" class="btn btn-secondary"><x-icon name="truck" class="text-brand-600" /> Registrar compra</a>
        @endruta
    @endcan
    @can('create', App\Modulos\Catalogo\Models\Producto::class)
        @ruta('productos.create')
            <a href="{{ route('productos.create') }}" class="btn btn-secondary"><x-icon name="package" class="text-brand-600" /> Nuevo producto</a>
        @endruta
    @else
        <a href="{{ route('productos.index') }}" class="btn btn-secondary"><x-icon name="search" class="text-brand-600" /> Consultar stock</a>
    @endcan

    <a href="{{ request()->fullUrl() }}" class="flex basis-full items-center gap-1.5 text-xs text-slate-500 hover:text-ink sm:ml-auto sm:basis-auto">
        <x-icon name="refresh" class="size-3.5" /> Actualizado a las {{ now()->format('H:i') }}
    </a>
</div>
