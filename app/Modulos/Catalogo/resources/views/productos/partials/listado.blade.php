@use('App\Modulos\Catalogo\Support\Unidades')
@php
    // "148.5 Kilogramo" o "Servicio"
    $stockTexto = fn ($p) => $p->es_servicio ? 'Servicio' : Unidades::conUnidad($p->stock_total ?? 0, $p->tipoUnidad?->descripcion);
    $agotado = fn ($p) => ! $p->es_servicio && (float) $p->stock_total <= 0;
@endphp

<section class="mt-6 rounded-xl border border-slate-200 bg-white shadow-sm" aria-labelledby="titulo-listado">
    <header class="p-4 sm:p-5">
        <h2 id="titulo-listado" class="text-base font-semibold">Catálogo de productos</h2>
        <p class="mt-0.5 text-xs text-slate-500">{{ number_format($productos->total()) }} {{ $productos->total() === 1 ? 'producto' : 'productos' }} · El stock se muestra en la unidad base de cada producto</p>
    </header>

    @if ($productos->isEmpty())
        <x-empty-state class="border-t border-slate-200" icon="package" title="No se encontraron productos" description="Crea el primero o prueba con otros filtros.">
            @can('create', App\Modulos\Catalogo\Models\Producto::class)
                <a href="{{ route('productos.create') }}" class="btn btn-primary"><x-icon name="plus" /> Nuevo producto</a>
            @endcan
        </x-empty-state>
    @else
        {{-- Tabla (≥ lg) --}}
        <div class="hidden overflow-x-auto lg:block">
            <table class="w-full text-sm">
                <thead class="border-y border-slate-200 bg-slate-50/70 text-left text-xs font-medium text-slate-500">
                    <tr>
                        <th class="py-3 pl-5 pr-3">Producto</th>
                        <th class="px-3 py-3">Unidad base</th>
                        <th class="px-3 py-3 text-right">Precio</th>
                        @if ($verCostos)<th class="px-3 py-3 text-right">Costo</th>@endif
                        <th class="px-3 py-3 text-right">Stock</th>
                        <th class="px-3 py-3">Estado</th>
                        <th class="py-3 pl-3 pr-5 text-center">Acciones</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach ($productos as $p)
                        <tr class="hover:bg-slate-50/60">
                            <td class="py-3 pl-5 pr-3">
                                <a href="{{ route('productos.show', $p) }}" class="font-semibold text-brand-600 hover:text-brand-700">{{ $p->nombre }}</a>
                                <p class="text-xs text-slate-500">{{ collect([$p->codigo, $p->categoria?->nombre])->filter()->implode(' · ') ?: '—' }}</p>
                            </td>
                            <td class="px-3 py-3">
                                {{ $p->tipoUnidad?->descripcion ?? '—' }}
                                @if ($p->presentaciones > 0)
                                    <p class="text-xs text-brand-700">+ {{ $p->presentaciones }} {{ $p->presentaciones === 1 ? 'presentación' : 'presentaciones' }}</p>
                                @endif
                            </td>
                            <td class="px-3 py-3 text-right font-semibold"><x-money :value="$p->precio_unitario" /></td>
                            @if ($verCostos)<td class="px-3 py-3 text-right text-slate-500"><x-money :value="$p->costo_unitario" /></td>@endif
                            <td class="px-3 py-3 text-right">
                                <p @class(['font-medium', 'text-red-600' => $agotado($p), 'text-amber-700' => $p->stock_bajo && ! $agotado($p)])>{{ $stockTexto($p) }}</p>
                                @if ($agotado($p))
                                    <x-badge tone="danger" shape="box">Agotado</x-badge>
                                @elseif ($p->stock_bajo)
                                    <x-badge tone="warning" shape="box">Stock bajo</x-badge>
                                @endif
                            </td>
                            <td class="px-3 py-3"><x-badge :tone="$p->activo ? 'success' : 'neutral'" :dot="true">{{ $p->activo ? 'Activo' : 'Inactivo' }}</x-badge></td>
                            <td class="py-3 pl-3 pr-5">
                                <div class="flex items-center justify-center gap-0.5">
                                    <a href="{{ route('productos.show', $p) }}" title="Ver ficha" aria-label="Ver {{ $p->nombre }}" class="grid size-8 place-items-center rounded-md text-brand-600 hover:bg-brand-50"><x-icon name="eye" /></a>
                                    @can('update', $p)
                                        <a href="{{ route('productos.edit', $p) }}" title="Editar" aria-label="Editar {{ $p->nombre }}" class="grid size-8 place-items-center rounded-md text-brand-600 hover:bg-brand-50"><x-icon name="pencil" /></a>
                                        <button type="button" data-eliminar="{{ route('productos.destroy', $p) }}" data-nombre="{{ $p->nombre }}" title="Eliminar" aria-label="Eliminar {{ $p->nombre }}" class="grid size-8 place-items-center rounded-md text-red-500 hover:bg-red-50"><x-icon name="trash" /></button>
                                    @endcan
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        {{-- Tarjetas (< lg) --}}
        <ul class="grid gap-3 border-t border-slate-200 p-4 sm:grid-cols-2 sm:p-5 lg:hidden">
            @foreach ($productos as $p)
                <li class="rounded-lg border border-slate-200 p-4">
                    <div class="flex items-start justify-between gap-2">
                        <div class="min-w-0">
                            <a href="{{ route('productos.show', $p) }}" class="block truncate text-sm font-bold text-brand-600">{{ $p->nombre }}</a>
                            <p class="text-xs text-slate-500">{{ collect([$p->codigo, $p->categoria?->nombre])->filter()->implode(' · ') ?: '—' }}</p>
                        </div>
                        <x-badge :tone="$p->activo ? 'success' : 'neutral'" :dot="true">{{ $p->activo ? 'Activo' : 'Inactivo' }}</x-badge>
                    </div>

                    <div class="mt-3 flex items-end justify-between gap-3">
                        <div>
                            <p class="text-[11px] text-slate-500">Stock</p>
                            <p @class(['text-sm font-semibold', 'text-red-600' => $agotado($p), 'text-amber-700' => $p->stock_bajo && ! $agotado($p)])>{{ $stockTexto($p) }}</p>
                            @if ($p->presentaciones > 0)
                                <p class="text-[11px] text-brand-700">+ {{ $p->presentaciones }} {{ $p->presentaciones === 1 ? 'presentación' : 'presentaciones' }}</p>
                            @endif
                        </div>
                        <div class="text-right">
                            <p class="text-[11px] text-slate-500">Precio / {{ $p->tipoUnidad?->descripcion ?? 'unidad' }}</p>
                            <p class="text-lg font-bold"><x-money :value="$p->precio_unitario" /></p>
                        </div>
                    </div>

                    <div class="mt-3 flex items-center justify-between gap-2 border-t border-slate-100 pt-3 text-xs font-semibold">
                        <a href="{{ route('productos.show', $p) }}" class="flex items-center gap-1.5 text-brand-600"><x-icon name="eye" class="size-3.5" /> Ver</a>
                        @can('update', $p)
                            <a href="{{ route('productos.edit', $p) }}" class="flex items-center gap-1.5 text-brand-600"><x-icon name="pencil" class="size-3.5" /> Editar</a>
                            <button type="button" data-eliminar="{{ route('productos.destroy', $p) }}" data-nombre="{{ $p->nombre }}" class="flex items-center gap-1.5 text-red-500"><x-icon name="trash" class="size-3.5" /> Eliminar</button>
                        @endcan
                    </div>
                </li>
            @endforeach
        </ul>

        <div class="border-t border-slate-200 px-4 py-3 sm:px-5">
            {{ $productos->links() }}
        </div>
    @endif
</section>
