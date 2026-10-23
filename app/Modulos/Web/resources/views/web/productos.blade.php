{{-- Página web → Productos publicados (ProductoWebController@index): $productos (paginador), $categorias, $publicados --}}
@extends('layouts.app')

@section('title', 'Productos publicados')

@section('content')
    <x-page-header title="Productos publicados" :migas="['Página web' => route('web.tienda.edit'), 'Productos publicados' => null]"
        subtitle="Solo los productos que publiques aparecen en tu página web. Agrega una foto y destaca los que más vendes.">
        <x-slot:acciones>
            <span class="text-sm text-slate-500">{{ $publicados }} {{ $publicados === 1 ? 'publicado' : 'publicados' }}</span>
        </x-slot:acciones>
    </x-page-header>

    <x-flash />

    <form method="GET" class="mt-6 flex flex-wrap items-end gap-3">
        <div class="min-w-56 flex-1"><x-input name="q" label="Buscar" :value="request('q')" placeholder="Nombre o código" /></div>
        <div class="w-48">
            <x-select name="categoria_id" label="Categoría">
                <option value="">Todas</option>
                @foreach ($categorias as $c)<option value="{{ $c->id }}" @selected((string) request('categoria_id') === (string) $c->id)>{{ $c->nombre }}</option>@endforeach
            </x-select>
        </div>
        <div class="w-44">
            <x-select name="ver" label="Mostrar">
                <option value="todos">Todos</option>
                <option value="publicados" @selected(request('ver') === 'publicados')>Publicados</option>
                <option value="sin_publicar" @selected(request('ver') === 'sin_publicar')>Sin publicar</option>
            </x-select>
        </div>
        <button type="submit" class="btn btn-secondary"><x-icon name="search" /> Filtrar</button>
    </form>

    <form id="masivo" method="POST" action="{{ route('web.productos.masivo') }}">@csrf</form>

    <section class="mt-4 overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
        <div class="flex flex-wrap items-center gap-2 border-b border-slate-100 bg-slate-50/70 px-4 py-2 text-xs">
            <span class="text-slate-500">Seleccionados:</span>
            <button type="submit" form="masivo" name="publicar" value="1" class="font-semibold text-brand-600 hover:underline">Publicar</button>
            <button type="submit" form="masivo" name="publicar" value="0" class="font-semibold text-slate-600 hover:underline">Ocultar</button>
        </div>
        <table class="w-full text-sm">
            <thead class="border-b border-slate-200 text-left text-xs font-medium text-slate-500">
                <tr>
                    <th class="w-10 py-3 pl-4"><input type="checkbox" aria-label="Seleccionar todos" onchange="document.querySelectorAll('[data-sel]').forEach(c => c.checked = this.checked)" class="rounded border-slate-300"></th>
                    <th class="px-3 py-3">Producto</th><th class="px-3 py-3 text-right">Precio</th><th class="px-3 py-3">Foto</th><th class="px-3 py-3">En la web</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($productos as $p)
                    <tr class="align-middle">
                        <td class="py-3 pl-4"><input type="checkbox" name="ids[]" value="{{ $p->id }}" form="masivo" data-sel class="rounded border-slate-300" aria-label="Seleccionar {{ $p->nombre }}"></td>
                        <td class="px-3 py-3">
                            <p class="font-medium">{{ $p->nombre }}</p>
                            <p class="text-xs text-slate-500">{{ $p->codigo ?: 'Sin código' }} · {{ $p->categoria?->nombre ?? 'Sin categoría' }}{{ $p->es_servicio ? ' · servicio' : '' }}</p>
                        </td>
                        <td class="px-3 py-3 text-right"><x-money :value="$p->precio_unitario" /></td>
                        <td class="px-3 py-3">
                            <form method="POST" action="{{ route('web.productos.update', $p) }}" enctype="multipart/form-data" class="flex items-center gap-2">
                                @csrf @method('PUT')
                                @if ($p->imagen_path)<x-icon name="image" class="text-emerald-600" />@endif
                                <input type="file" name="imagen" accept="image/jpeg,image/png,image/webp" onchange="this.form.submit()" class="w-44 text-xs file:mr-2 file:rounded-md file:border-0 file:bg-slate-100 file:px-2 file:py-1 file:text-xs" aria-label="Foto de {{ $p->nombre }}">
                                @if ($p->imagen_path)<button name="quitar_imagen" value="1" class="text-xs text-red-500 hover:underline">Quitar</button>@endif
                            </form>
                        </td>
                        <td class="px-3 py-3">
                            <form method="POST" action="{{ route('web.productos.update', $p) }}" class="flex flex-wrap items-center gap-3">
                                @csrf @method('PUT')
                                <button name="publicar_web" value="{{ $p->publicar_web ? 0 : 1 }}" @class(['rounded-full px-2.5 py-1 text-xs font-semibold', 'bg-emerald-50 text-emerald-700' => $p->publicar_web, 'bg-slate-100 text-slate-500' => ! $p->publicar_web])>
                                    {{ $p->publicar_web ? 'Publicado' : 'No publicado' }}
                                </button>
                                @if ($p->publicar_web)
                                    <button name="destacado_web" value="{{ $p->destacado_web ? 0 : 1 }}" class="text-xs {{ $p->destacado_web ? 'font-semibold text-amber-600' : 'text-slate-400' }} hover:underline">★ {{ $p->destacado_web ? 'Destacado' : 'Destacar' }}</button>
                                @endif
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-4 py-10 text-center text-sm text-slate-500">No hay productos con esos filtros.</td></tr>
                @endforelse
            </tbody>
        </table>
        @if ($productos->hasPages())<div class="border-t border-slate-100 px-4 py-3">{{ $productos->links() }}</div>@endif
    </section>
@endsection
