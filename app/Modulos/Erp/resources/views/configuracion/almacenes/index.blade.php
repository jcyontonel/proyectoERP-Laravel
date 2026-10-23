{{-- AlmacenController@index: $almacenes (productos, unidades), $valores [almacen_id => valor] --}}
@extends('layouts.app')

@section('title', 'Almacenes')

@section('content')
    <x-page-header title="Almacenes" :migas="['Configuración' => null, 'Almacenes' => null]" subtitle="Sedes y depósitos donde guardas stock. El principal se usa por defecto en ventas y compras.">
        <x-slot:acciones>
            <a href="{{ route('cajas.index') }}" class="btn btn-secondary"><x-icon name="wallet" class="text-brand-600" /> Cajas</a>
            <a href="{{ route('almacenes.create') }}" class="btn btn-primary"><x-icon name="plus" /> Nuevo almacén</a>
        </x-slot:acciones>
    </x-page-header>

    <x-flash />

    <section class="mt-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
        @forelse ($almacenes as $a)
            <article @class(['rounded-xl border bg-white p-5 shadow-sm', 'border-brand-200' => $a->es_principal, 'border-slate-200' => ! $a->es_principal, 'opacity-60' => ! $a->activo])>
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        <p class="text-xs font-medium text-slate-500">{{ $a->codigo }}</p>
                        <h2 class="truncate text-base font-semibold">{{ $a->nombre }}</h2>
                    </div>
                    <div class="flex flex-wrap justify-end gap-1">
                        @if ($a->es_principal)<x-badge tone="info">Principal</x-badge>@endif
                        @unless ($a->activo)<x-badge tone="neutral">Inactivo</x-badge>@endunless
                    </div>
                </div>
                <p class="mt-1 flex items-center gap-1.5 text-xs text-slate-500"><x-icon name="map-pin" class="size-3.5" /> {{ $a->direccion ?: 'Sin dirección' }}</p>
                <dl class="mt-4 grid grid-cols-2 gap-3 text-sm">
                    <div><dt class="text-xs text-slate-500">Productos con stock</dt><dd class="font-semibold">{{ number_format($a->productos) }}</dd></div>
                    <div><dt class="text-xs text-slate-500">Valor al costo</dt><dd class="font-semibold"><x-money :value="$valores[$a->id] ?? 0" /></dd></div>
                </dl>
                <div class="mt-4 flex items-center justify-between border-t border-slate-100 pt-3 text-xs font-semibold">
                    <a href="{{ route('kardex.index', ['almacen_id' => $a->id]) }}" class="text-brand-600 hover:text-brand-700">Ver kardex</a>
                    <span class="flex gap-3">
                        <a href="{{ route('almacenes.edit', $a) }}" class="text-brand-600 hover:text-brand-700">Editar</a>
                        @unless ($a->es_principal)
                            <button type="button" data-eliminar="{{ route('almacenes.destroy', $a) }}" data-nombre="{{ $a->nombre }}" class="text-red-500 hover:text-red-600">Eliminar</button>
                        @endunless
                    </span>
                </div>
            </article>
        @empty
            <x-empty-state icon="warehouse" title="Sin almacenes" description="Crea el almacén donde guardas tu mercadería." class="sm:col-span-2 xl:col-span-3" />
        @endforelse
    </section>

    <x-dialogo-eliminar titulo="Eliminar almacén" mensaje="Solo se eliminan almacenes que nunca tuvieron stock." />
@endsection
