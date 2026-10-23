{{-- CategoriaController@index: $categorias (productos_count, activos_count), $sinCategoria --}}
@extends('layouts.app')

@section('title', 'Categorías')

@section('content')
    <x-page-header title="Categorías" :migas="['Inventario' => null, 'Categorías' => null]" subtitle="Familias de tu catálogo: ordenan la búsqueda de productos y los reportes de ventas.">
        <x-slot:acciones>
            <a href="{{ route('categorias.create') }}" class="btn btn-primary"><x-icon name="plus" /> Nueva categoría</a>
        </x-slot:acciones>
    </x-page-header>

    <x-flash />

    @if ($sinCategoria)
        <x-alert tone="info" class="mt-4">{{ $sinCategoria }} {{ $sinCategoria === 1 ? 'producto no tiene' : 'productos no tienen' }} categoría.</x-alert>
    @endif

    <form method="GET" class="mt-6 flex gap-3">
        <div class="relative flex-1">
            <x-icon name="search" class="pointer-events-none absolute left-3 top-3 text-slate-400" />
            <input name="q" type="search" value="{{ request('q') }}" placeholder="Buscar categoría…" aria-label="Buscar categoría" class="field pl-9">
        </div>
        <button type="submit" class="btn btn-secondary">Buscar</button>
    </form>

    <section class="mt-4 overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
        @if ($categorias->isEmpty())
            <x-empty-state icon="tag" title="Sin categorías" description="Crea categorías para organizar tus productos." />
        @else
            <ul class="divide-y divide-slate-100">
                @foreach ($categorias as $c)
                    <li class="flex items-center gap-4 px-4 py-3 sm:px-5">
                        <span class="grid size-9 shrink-0 place-items-center rounded-lg bg-brand-50 text-brand-600"><x-icon name="tag" /></span>
                        <div class="min-w-0 flex-1">
                            <a href="{{ route('productos.index', ['categoria_id' => $c->id]) }}" class="font-semibold hover:text-brand-600">{{ $c->nombre }}</a>
                            <p class="truncate text-xs text-slate-500">{{ $c->descripcion ?: 'Sin descripción' }}</p>
                        </div>
                        <span class="hidden text-right text-xs text-slate-500 sm:block">{{ $c->productos_count }} productos<br>{{ $c->activos_count }} activos</span>
                        <div class="flex gap-0.5">
                            <a href="{{ route('categorias.edit', $c) }}" title="Editar" aria-label="Editar {{ $c->nombre }}" class="grid size-8 place-items-center rounded-md text-brand-600 hover:bg-brand-50"><x-icon name="pencil" /></a>
                            <button type="button" data-eliminar="{{ route('categorias.destroy', $c) }}" data-nombre="{{ $c->nombre }}" title="Eliminar" aria-label="Eliminar {{ $c->nombre }}" class="grid size-8 place-items-center rounded-md text-red-500 hover:bg-red-50"><x-icon name="trash" /></button>
                        </div>
                    </li>
                @endforeach
            </ul>
            <div class="border-t border-slate-200 px-4 py-3 sm:px-5">{{ $categorias->links() }}</div>
        @endif
    </section>

    <x-dialogo-eliminar titulo="Eliminar categoría" mensaje="Solo se eliminan categorías sin productos." />
@endsection
