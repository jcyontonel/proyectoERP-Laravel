{{-- Accesos rápidos según permisos --}}
<div class="mt-5 flex flex-wrap items-center gap-3">
    @can('emitir-ventas')
        <a href="{{ route('ventas.create') }}" class="btn btn-primary"><x-icon name="plus" /> Nueva venta</a>
    @endcan
    @can('gestionar-caja')
        <a href="{{ route('caja.index') }}" class="btn btn-secondary"><x-icon name="wallet" class="text-brand-600" /> {{ ($caja['abierta'] ?? false) ? 'Cerrar caja' : 'Abrir caja' }}</a>
    @endcan
    @can('create', App\Models\Cliente::class)
        <a href="{{ route('clientes.create') }}" class="btn btn-secondary"><x-icon name="user" class="text-brand-600" /> Registrar cliente</a>
    @endcan
    @can('gestionar-compras')
        <a href="{{ route('compras.create') }}" class="btn btn-secondary"><x-icon name="truck" class="text-brand-600" /> Registrar compra</a>
    @endcan
    @can('create', App\Models\Producto::class)
        <a href="{{ route('productos.create') }}" class="btn btn-secondary"><x-icon name="package" class="text-brand-600" /> Nuevo producto</a>
    @else
        <a href="{{ route('productos.index') }}" class="btn btn-secondary"><x-icon name="search" class="text-brand-600" /> Consultar stock</a>
    @endcan

    <a href="{{ request()->fullUrl() }}" class="flex basis-full items-center gap-1.5 text-xs text-slate-500 hover:text-ink sm:ml-auto sm:basis-auto">
        <x-icon name="refresh" class="size-3.5" /> Actualizado a las {{ now()->format('H:i') }}
    </a>
</div>
