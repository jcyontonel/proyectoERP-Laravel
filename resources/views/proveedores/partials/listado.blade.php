<section class="mt-6 rounded-xl border border-slate-200 bg-white shadow-sm" aria-labelledby="titulo-listado">
    <header class="p-4 sm:p-5">
        <h2 id="titulo-listado" class="text-base font-semibold">Listado de proveedores</h2>
        <p class="mt-0.5 text-xs text-slate-500">{{ number_format($proveedores->total()) }} proveedores</p>
    </header>

    @if ($proveedores->isEmpty())
        <div class="border-t border-slate-200 px-4 py-14 text-center sm:px-5">
            <p class="text-sm font-medium">No hay proveedores</p>
            <p class="mt-1 text-xs text-slate-500">Crea el primero o prueba con otra búsqueda.</p>
        </div>
    @else
        {{-- Tabla (≥ lg) --}}
        <div class="hidden overflow-x-auto lg:block">
            <table class="w-full text-sm">
                <thead class="border-y border-slate-200 bg-slate-50/70 text-left text-xs font-medium text-slate-500">
                    <tr>
                        <th class="py-3 pl-5 pr-3">Proveedor</th>
                        <th class="px-3 py-3">Documento</th>
                        <th class="px-3 py-3">Contacto</th>
                        <th class="px-3 py-3">Teléfono / correo</th>
                        <th class="px-3 py-3 text-right">Compras</th>
                        <th class="px-3 py-3">Estado</th>
                        <th class="py-3 pl-3 pr-5 text-center">Acciones</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach ($proveedores as $p)
                        <tr class="hover:bg-slate-50/60">
                            <td class="py-3 pl-5 pr-3">
                                <a href="{{ route('proveedores.edit', $p) }}" class="font-semibold text-brand-600 hover:text-brand-700">{{ $p->nombre }}</a>
                                @if ($p->nombre_comercial && $p->razon_social)
                                    <p class="text-xs text-slate-500">{{ $p->nombre_comercial }}</p>
                                @endif
                            </td>
                            <td class="px-3 py-3 whitespace-nowrap">{{ $p->documento_etiqueta }}</td>
                            <td class="px-3 py-3">
                                {{ $p->contacto_nombre ?: '—' }}
                                @if ($p->contacto_cargo) <p class="text-xs text-slate-500">{{ $p->contacto_cargo }}</p> @endif
                            </td>
                            <td class="px-3 py-3">
                                {{ collect([$p->telefono, $p->celular])->filter()->implode(' / ') ?: '—' }}
                                @if ($p->email) <p class="break-all text-xs text-slate-500">{{ $p->email }}</p> @endif
                            </td>
                            <td class="px-3 py-3 text-right">{{ $p->compras_count }}</td>
                            <td class="px-3 py-3"><x-badge :tone="$p->activo ? 'success' : 'neutral'" :dot="true">{{ $p->activo ? 'Activo' : 'Inactivo' }}</x-badge></td>
                            <td class="py-3 pl-3 pr-5">
                                <div class="flex items-center justify-center gap-0.5">
                                    <a href="{{ route('proveedores.edit', $p) }}" title="Editar" aria-label="Editar {{ $p->nombre }}" class="grid size-8 place-items-center rounded-md text-brand-600 hover:bg-brand-50"><x-icon name="pencil" /></a>
                                    <form method="POST" action="{{ route('proveedores.destroy', $p) }}" onsubmit="return confirm('¿Eliminar a {{ e(addslashes($p->nombre)) }}?')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" title="Eliminar" aria-label="Eliminar {{ $p->nombre }}" class="grid size-8 place-items-center rounded-md text-red-500 hover:bg-red-50"><x-icon name="trash" /></button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        {{-- Tarjetas (< lg) --}}
        <ul class="grid gap-3 border-t border-slate-200 p-4 sm:grid-cols-2 sm:p-5 lg:hidden">
            @foreach ($proveedores as $p)
                <li class="rounded-lg border border-slate-200 p-4">
                    <div class="flex items-start justify-between gap-2">
                        <div class="min-w-0">
                            <a href="{{ route('proveedores.edit', $p) }}" class="block truncate text-sm font-bold text-brand-600">{{ $p->nombre }}</a>
                            <p class="text-xs text-slate-500">{{ $p->documento_etiqueta }}</p>
                        </div>
                        <x-badge :tone="$p->activo ? 'success' : 'neutral'" :dot="true">{{ $p->activo ? 'Activo' : 'Inactivo' }}</x-badge>
                    </div>

                    <p class="mt-3 text-xs text-slate-600">
                        {{ $p->contacto_nombre ?: 'Sin contacto' }}
                        @if ($p->telefono || $p->celular) · {{ collect([$p->telefono, $p->celular])->filter()->implode(' / ') }} @endif
                    </p>
                    @if ($p->email) <p class="break-all text-xs text-slate-500">{{ $p->email }}</p> @endif

                    <div class="mt-3 flex items-center justify-between gap-2 border-t border-slate-100 pt-3 text-xs font-semibold">
                        <span class="font-normal text-slate-500">{{ $p->compras_count }} {{ $p->compras_count === 1 ? 'compra' : 'compras' }}</span>
                        <div class="flex items-center gap-4">
                            <a href="{{ route('proveedores.edit', $p) }}" class="flex items-center gap-1.5 text-brand-600"><x-icon name="pencil" class="size-3.5" /> Editar</a>
                            <form method="POST" action="{{ route('proveedores.destroy', $p) }}" onsubmit="return confirm('¿Eliminar a {{ e(addslashes($p->nombre)) }}?')">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="flex items-center gap-1.5 text-red-500"><x-icon name="trash" class="size-3.5" /> Eliminar</button>
                            </form>
                        </div>
                    </div>
                </li>
            @endforeach
        </ul>

        <div class="border-t border-slate-200 px-4 py-3 sm:px-5">
            {{ $proveedores->links('vendor.pagination.ricopet') }}
        </div>
    @endif
</section>
