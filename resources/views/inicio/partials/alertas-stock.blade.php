{{-- $stock: total, items [id, nombre, codigo, almacen, stock, minimo, unidad, agotado] --}}
<x-card title="Stock crítico" icon="alert-triangle">
    <x-slot:aside>
        <x-badge :tone="$stock['total'] ? 'warning' : 'success'" shape="box">{{ $stock['total'] }} {{ $stock['total'] === 1 ? 'alerta' : 'alertas' }}</x-badge>
    </x-slot:aside>

    @if ($stock['total'])
        <ul class="divide-y divide-slate-100">
            @foreach ($stock['items'] as $a)
                <li class="flex items-center gap-3 py-3">
                    <span @class(['grid size-8 shrink-0 place-items-center rounded-md', 'bg-red-50 text-red-500' => $a->agotado, 'bg-amber-50 text-amber-600' => ! $a->agotado])>
                        <x-icon name="package" />
                    </span>
                    <div class="min-w-0 flex-1 leading-tight">
                        <a href="{{ route('productos.show', $a->id) }}" class="block truncate text-sm font-medium hover:text-brand-600">{{ $a->nombre }}</a>
                        <p @class(['mt-0.5 text-[11px]', 'text-red-600' => $a->agotado, 'text-amber-700' => ! $a->agotado])>
                            {{ $a->agotado ? 'Agotado' : 'Stock ' . $a->stock }} · Mín. {{ $a->minimo }} {{ $a->unidad }} · {{ $a->almacen }}
                        </p>
                    </div>
                    @can('gestionar-compras')
                        @ruta('compras.create')
                            <a href="{{ route('compras.create') }}" class="shrink-0 text-xs font-semibold text-brand-600 hover:text-brand-700">Reabastecer</a>
                        @endruta
                    @endcan
                </li>
            @endforeach
        </ul>

        <a href="{{ route('productos.index', ['stock' => 'bajo']) }}" class="mt-1 flex items-center justify-between border-t border-slate-200 pt-3 text-xs font-semibold text-brand-600 hover:text-brand-700">
            Ver todos los productos con stock bajo <x-icon name="arrow-right" class="size-3.5" />
        </a>
    @else
        <p class="rounded-lg bg-emerald-50 px-3 py-3 text-xs text-emerald-700">Todo el inventario está sobre el stock mínimo.</p>
    @endif
</x-card>
