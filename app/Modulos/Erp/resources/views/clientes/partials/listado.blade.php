@php
    $ultima = fn ($c) => $c->ultima_compra ? \Illuminate\Support\Carbon::parse($c->ultima_compra)->diffForHumans() : 'Sin compras';
@endphp

<section class="mt-6 rounded-xl border border-slate-200 bg-white shadow-sm" aria-labelledby="titulo-clientes">
    <header class="p-4 sm:p-5">
        <h2 id="titulo-clientes" class="text-base font-semibold">Cartera de clientes</h2>
        <p class="mt-0.5 text-xs text-slate-500">{{ number_format($clientes->total()) }} {{ $clientes->total() === 1 ? 'cliente' : 'clientes' }} · Compras y montos solo de tu empresa</p>
    </header>

    @if ($clientes->isEmpty())
        <x-empty-state class="border-t border-slate-200" icon="users" title="No se encontraron clientes" description="Registra el primero o prueba con otros filtros.">
            @can('create', App\Modulos\Erp\Models\Cliente::class)
                <a href="{{ route('clientes.create') }}" class="btn btn-primary"><x-icon name="plus" /> Nuevo cliente</a>
            @endcan
        </x-empty-state>
    @else
        {{-- Tabla (≥ lg) --}}
        <div class="hidden overflow-x-auto lg:block">
            <table class="w-full text-sm">
                <thead class="border-y border-slate-200 bg-slate-50/70 text-left text-xs font-medium text-slate-500">
                    <tr>
                        <th class="py-3 pl-5 pr-3">Cliente</th>
                        <th class="px-3 py-3">Documento</th>
                        <th class="px-3 py-3">Contacto</th>
                        <th class="px-3 py-3 text-right">Compras</th>
                        <th class="px-3 py-3 text-right">Total comprado</th>
                        <th class="px-3 py-3">Estado</th>
                        <th class="py-3 pl-3 pr-5 text-center">Acciones</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach ($clientes as $c)
                        <tr class="hover:bg-slate-50/60">
                            <td class="py-3 pl-5 pr-3">
                                <div class="flex items-center gap-3">
                                    <span class="grid size-9 shrink-0 place-items-center rounded-full bg-brand-50 text-xs font-semibold text-brand-700">{{ $c->iniciales }}</span>
                                    <div class="min-w-0">
                                        <a href="{{ route('clientes.show', $c) }}" class="font-semibold text-brand-600 hover:text-brand-700">{{ $c->nombre_completo }}</a>
                                        <p class="text-xs text-slate-500">Última compra: {{ $ultima($c) }}</p>
                                    </div>
                                </div>
                            </td>
                            <td class="px-3 py-3 whitespace-nowrap">{{ $c->documento_etiqueta }}</td>
                            <td class="px-3 py-3">
                                <p class="text-slate-700">{{ $c->telefono ?: '—' }}</p>
                                <p class="text-xs text-slate-500">{{ $c->correo }}</p>
                            </td>
                            <td class="px-3 py-3 text-right">{{ number_format($c->ventas_count) }}</td>
                            <td class="px-3 py-3 text-right font-semibold"><x-money :value="$c->vendido ?? 0" /></td>
                            <td class="px-3 py-3"><x-badge :tone="$c->activo ? 'success' : 'neutral'" :dot="true">{{ $c->activo ? 'Activo' : 'Inactivo' }}</x-badge></td>
                            <td class="py-3 pl-3 pr-5">
                                <div class="flex items-center justify-center gap-0.5">
                                    <a href="{{ route('clientes.show', $c) }}" title="Ver ficha" aria-label="Ver {{ $c->nombre_completo }}" class="grid size-8 place-items-center rounded-md text-brand-600 hover:bg-brand-50"><x-icon name="eye" /></a>
                                    @can('gestionar-clientes')
                                        <a href="{{ route('clientes.edit', $c) }}" title="Editar" aria-label="Editar {{ $c->nombre_completo }}" class="grid size-8 place-items-center rounded-md text-brand-600 hover:bg-brand-50"><x-icon name="pencil" /></a>
                                        <button type="button" data-eliminar="{{ route('clientes.destroy', $c) }}" data-nombre="{{ $c->nombre_completo }}" title="Quitar" aria-label="Quitar {{ $c->nombre_completo }}" class="grid size-8 place-items-center rounded-md text-red-500 hover:bg-red-50"><x-icon name="trash" /></button>
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
            @foreach ($clientes as $c)
                <li class="rounded-lg border border-slate-200 p-4">
                    <div class="flex items-start gap-3">
                        <span class="grid size-9 shrink-0 place-items-center rounded-full bg-brand-50 text-xs font-semibold text-brand-700">{{ $c->iniciales }}</span>
                        <div class="min-w-0 flex-1">
                            <a href="{{ route('clientes.show', $c) }}" class="block truncate text-sm font-bold text-brand-600">{{ $c->nombre_completo }}</a>
                            <p class="text-xs text-slate-500">{{ $c->documento_etiqueta }}</p>
                        </div>
                        <x-badge :tone="$c->activo ? 'success' : 'neutral'" :dot="true">{{ $c->activo ? 'Activo' : 'Inactivo' }}</x-badge>
                    </div>
                    <div class="mt-3 flex items-end justify-between gap-3 text-xs text-slate-500">
                        <span>{{ number_format($c->ventas_count) }} {{ $c->ventas_count === 1 ? 'compra' : 'compras' }} · {{ $ultima($c) }}</span>
                        <span class="text-sm font-bold text-ink"><x-money :value="$c->vendido ?? 0" /></span>
                    </div>
                </li>
            @endforeach
        </ul>

        <div class="border-t border-slate-200 px-4 py-3 sm:px-5">
            {{ $clientes->links() }}
        </div>
    @endif
</section>
