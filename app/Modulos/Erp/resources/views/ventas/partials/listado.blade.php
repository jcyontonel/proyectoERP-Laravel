@php
    // Acciones por fila: solo las completadas se pueden anular
    $puedeAnular = fn ($v) => $v->estado === \App\Modulos\Erp\Models\Venta::COMPLETADA;
@endphp

<section class="mt-6 rounded-xl border border-slate-200 bg-white shadow-sm" aria-labelledby="titulo-listado">
    <header class="flex flex-wrap items-start justify-between gap-3 p-4 sm:p-5">
        <div>
            <h2 id="titulo-listado" class="text-base font-semibold">Listado de ventas</h2>
            <p class="mt-0.5 text-xs text-slate-500">{{ number_format($ventas->total()) }} comprobantes · Del más reciente al más antiguo</p>
        </div>
        <ul class="hidden items-center gap-4 text-[11px] text-slate-500 lg:flex" aria-label="Leyenda de acciones">
            <li class="flex items-center gap-1.5"><x-icon name="eye" class="size-3.5 text-brand-600" /> Ver Detalle</li>
            <li class="flex items-center gap-1.5"><x-icon name="printer" class="size-3.5 text-brand-600" /> Imprimir Ticket</li>
            <li class="flex items-center gap-1.5"><x-icon name="x-circle" class="size-3.5 text-red-500" /> Anular</li>
        </ul>
    </header>

    @if ($ventas->isEmpty())
        <div class="border-t border-slate-200 px-4 py-14 text-center sm:px-5">
            <p class="text-sm font-medium">No se encontraron ventas</p>
            <p class="mt-1 text-xs text-slate-500">Prueba con otros filtros o un rango de fechas distinto.</p>
        </div>
    @else
        {{-- Tabla (≥ lg) --}}
        <div class="hidden overflow-x-auto lg:block">
            <table class="w-full text-sm">
                <thead class="border-y border-slate-200 bg-slate-50/70 text-left text-xs font-medium text-slate-500">
                    <tr>
                        <th class="py-3 pl-5 pr-3">N° Comprobante</th>
                        <th class="px-3 py-3">Fecha y Hora</th>
                        <th class="px-3 py-3">Cliente</th>
                        <th class="px-3 py-3">Tipo</th>
                        <th class="px-3 py-3">Método de Pago</th>
                        <th class="px-3 py-3 text-right">Total</th>
                        <th class="px-3 py-3">Estado</th>
                        <th class="py-3 pl-3 pr-5 text-center">Acciones</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach ($ventas as $v)
                        <tr class="hover:bg-slate-50/60">
                            <td class="py-3 pl-5 pr-3">
                                <a href="{{ route('ventas.show', $v->id) }}" class="font-semibold text-brand-600 hover:text-brand-700">{{ $v->numero }}</a>
                            </td>
                            <td class="px-3 py-3">
                                <p>{{ $v->fecha->translatedFormat('d M Y') }}</p>
                                <p class="text-xs text-slate-500">{{ $v->fecha->format('h:i A') }}</p>
                            </td>
                            <td class="px-3 py-3">
                                <p class="font-medium">{{ $v->cliente_nombre }}</p>
                                <p class="text-xs text-slate-500">{{ $v->cliente_doc }}</p>
                            </td>
                            <td class="px-3 py-3"><x-badge tone="info" shape="box">{{ $v->tipo }}</x-badge></td>
                            <td class="px-3 py-3"><x-metodo-pago :metodo="$v->metodo" :label="$v->metodo_label" /></td>
                            <td class="px-3 py-3 text-right font-semibold"><x-money :value="$v->total" /></td>
                            <td class="px-3 py-3"><x-estado-venta :estado="$v->estado" /></td>
                            <td class="py-3 pl-3 pr-5">
                                <div class="flex items-center justify-center gap-0.5">
                                    <a href="{{ route('ventas.show', $v->id) }}" title="Ver detalle" aria-label="Ver detalle de {{ $v->numero }}" class="grid size-8 place-items-center rounded-md text-brand-600 hover:bg-brand-50"><x-icon name="eye" /></a>
                                    <a href="{{ route('ventas.ticket', $v->id) }}" target="_blank" rel="noopener" title="Imprimir ticket" aria-label="Imprimir ticket de {{ $v->numero }}" class="grid size-8 place-items-center rounded-md text-brand-600 hover:bg-brand-50"><x-icon name="printer" /></a>
                                    @if ($puedeAnular($v))
                                        <button type="button" data-anular="{{ route('ventas.anular', $v->id) }}" data-numero="{{ $v->numero }}" title="Anular" aria-label="Anular {{ $v->numero }}" class="grid size-8 place-items-center rounded-md text-red-500 hover:bg-red-50"><x-icon name="x-circle" /></button>
                                    @else
                                        <span title="No se puede anular" aria-disabled="true" class="grid size-8 place-items-center text-slate-300"><x-icon name="x-circle" /></span>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        {{-- Tarjetas (< lg) --}}
        <ul class="grid gap-3 border-t border-slate-200 p-4 sm:grid-cols-2 sm:p-5 lg:hidden">
            @foreach ($ventas as $v)
                <li class="rounded-lg border border-slate-200 p-4">
                    <div class="flex items-center justify-between gap-2">
                        <a href="{{ route('ventas.show', $v->id) }}" class="text-sm font-bold text-brand-600">{{ $v->numero }}</a>
                        <x-estado-venta :estado="$v->estado" />
                    </div>
                    <p class="mt-1 text-xs text-slate-500">{{ $v->fecha->translatedFormat('d M Y') }} · {{ $v->fecha->format('h:i A') }}</p>

                    <div class="mt-3 flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <p class="truncate text-sm font-semibold">{{ $v->cliente_nombre }}</p>
                            <p class="text-xs text-slate-500">{{ $v->cliente_doc }}</p>
                        </div>
                        <x-badge tone="info" shape="box">{{ $v->tipo }}</x-badge>
                    </div>

                    <div class="mt-3 flex items-end justify-between gap-3">
                        <div>
                            <p class="mb-0.5 text-[11px] text-slate-500">Método de pago</p>
                            <x-metodo-pago :metodo="$v->metodo" :label="$v->metodo_label" />
                        </div>
                        <div class="text-right">
                            <p class="text-[11px] text-slate-500">Total</p>
                            <p class="text-lg font-bold"><x-money :value="$v->total" /></p>
                        </div>
                    </div>

                    <div class="mt-3 flex items-center justify-between gap-2 border-t border-slate-100 pt-3 text-xs font-semibold">
                        <a href="{{ route('ventas.show', $v->id) }}" class="flex items-center gap-1.5 text-brand-600"><x-icon name="eye" class="size-3.5" /> Ver Detalle</a>
                        <a href="{{ route('ventas.ticket', $v->id) }}" target="_blank" rel="noopener" class="flex items-center gap-1.5 text-brand-600"><x-icon name="printer" class="size-3.5" /> Imprimir Ticket</a>
                        @if ($puedeAnular($v))
                            <button type="button" data-anular="{{ route('ventas.anular', $v->id) }}" data-numero="{{ $v->numero }}" class="flex items-center gap-1.5 text-red-500"><x-icon name="x-circle" class="size-3.5" /> Anular</button>
                        @else
                            <span aria-disabled="true" class="flex items-center gap-1.5 text-slate-300"><x-icon name="x-circle" class="size-3.5" /> Anular</span>
                        @endif
                    </div>
                </li>
            @endforeach
        </ul>

        <div class="border-t border-slate-200 px-4 py-3 sm:px-5">
            {{ $ventas->links('vendor.pagination.ricopet') }}
        </div>
    @endif
</section>
