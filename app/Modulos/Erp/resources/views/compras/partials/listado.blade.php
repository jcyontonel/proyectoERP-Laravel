<section class="mt-6 rounded-xl border border-slate-200 bg-white shadow-sm" aria-labelledby="titulo-listado">
    <header class="flex flex-wrap items-start justify-between gap-3 p-4 sm:p-5">
        <div>
            <h2 id="titulo-listado" class="text-base font-semibold">Listado de compras</h2>
            <p class="mt-0.5 text-xs text-slate-500">{{ number_format($compras->total()) }} compras · Del más reciente al más antiguo</p>
        </div>
        <div class="flex items-center gap-2 rounded-lg bg-amber-50 px-3 py-1.5 text-xs text-amber-800">
            <x-icon name="clock" class="size-3.5" /> Por pagar (según filtros): <strong><x-money :value="$pendiente" /></strong>
        </div>
    </header>

    @if ($compras->isEmpty())
        <div class="border-t border-slate-200 px-4 py-14 text-center sm:px-5">
            <p class="text-sm font-medium">No se encontraron compras</p>
            <p class="mt-1 text-xs text-slate-500">Registra tu primera compra o prueba con otros filtros.</p>
        </div>
    @else
        {{-- Tabla (≥ lg) --}}
        <div class="hidden overflow-x-auto lg:block">
            <table class="w-full text-sm">
                <thead class="border-y border-slate-200 bg-slate-50/70 text-left text-xs font-medium text-slate-500">
                    <tr>
                        <th class="py-3 pl-5 pr-3">Comprobante</th>
                        <th class="px-3 py-3">Fecha</th>
                        <th class="px-3 py-3">Proveedor</th>
                        <th class="px-3 py-3">Almacén</th>
                        <th class="px-3 py-3 text-right">Total</th>
                        <th class="px-3 py-3">Pago</th>
                        <th class="px-3 py-3">Estado</th>
                        <th class="py-3 pl-3 pr-5 text-center">Acciones</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach ($compras as $c)
                        <tr class="hover:bg-slate-50/60">
                            <td class="py-3 pl-5 pr-3">
                                <a href="{{ route('compras.show', $c) }}" class="font-semibold text-brand-600 hover:text-brand-700">{{ $c->numero_completo }}</a>
                                <p class="text-xs text-slate-500">{{ $c->tipo_etiqueta }}</p>
                            </td>
                            <td class="px-3 py-3 whitespace-nowrap">{{ $c->fecha_emision->translatedFormat('d M Y') }}</td>
                            <td class="px-3 py-3">
                                <p class="font-medium">{{ $c->proveedor?->nombre ?? '—' }}</p>
                                <p class="text-xs text-slate-500">{{ $c->proveedor?->documento_etiqueta }}</p>
                            </td>
                            <td class="px-3 py-3 text-slate-600">{{ $c->almacen?->nombre ?? '—' }}</td>
                            <td class="px-3 py-3 text-right font-semibold"><x-money :value="$c->total" /></td>
                            <td class="px-3 py-3">
                                <x-estado-pago :estado="$c->estadoPago()" />
                                @if ($c->esRegistrada() && $c->saldo() > 0)
                                    <p class="mt-0.5 text-[11px] text-slate-500">Saldo <x-money :value="$c->saldo()" /></p>
                                @endif
                            </td>
                            <td class="px-3 py-3"><x-estado-compra :estado="$c->estado" /></td>
                            <td class="py-3 pl-3 pr-5">
                                <div class="flex items-center justify-center gap-0.5">
                                    <a href="{{ route('compras.show', $c) }}" title="Ver detalle" aria-label="Ver detalle de {{ $c->numero_completo }}" class="grid size-8 place-items-center rounded-md text-brand-600 hover:bg-brand-50"><x-icon name="eye" /></a>
                                    @if ($c->esAnulable())
                                        <button type="button" data-anular="{{ route('compras.anular', $c) }}" data-numero="{{ $c->numero_completo }}" title="Anular" aria-label="Anular {{ $c->numero_completo }}" class="grid size-8 place-items-center rounded-md text-red-500 hover:bg-red-50"><x-icon name="x-circle" /></button>
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
            @foreach ($compras as $c)
                <li class="rounded-lg border border-slate-200 p-4">
                    <div class="flex items-center justify-between gap-2">
                        <a href="{{ route('compras.show', $c) }}" class="text-sm font-bold text-brand-600">{{ $c->numero_completo }}</a>
                        <x-estado-compra :estado="$c->estado" />
                    </div>
                    <p class="mt-1 text-xs text-slate-500">{{ $c->tipo_etiqueta }} · {{ $c->fecha_emision->translatedFormat('d M Y') }}</p>

                    <div class="mt-3 min-w-0">
                        <p class="truncate text-sm font-semibold">{{ $c->proveedor?->nombre ?? '—' }}</p>
                        <p class="text-xs text-slate-500">{{ $c->proveedor?->documento_etiqueta }} · {{ $c->almacen?->nombre }}</p>
                    </div>

                    <div class="mt-3 flex items-end justify-between gap-3">
                        <div>
                            <p class="mb-0.5 text-[11px] text-slate-500">Pago</p>
                            <x-estado-pago :estado="$c->estadoPago()" />
                        </div>
                        <div class="text-right">
                            <p class="text-[11px] text-slate-500">Total</p>
                            <p class="text-lg font-bold"><x-money :value="$c->total" /></p>
                        </div>
                    </div>

                    <div class="mt-3 flex items-center justify-between gap-2 border-t border-slate-100 pt-3 text-xs font-semibold">
                        <a href="{{ route('compras.show', $c) }}" class="flex items-center gap-1.5 text-brand-600"><x-icon name="eye" class="size-3.5" /> Ver detalle</a>
                        @if ($c->esAnulable())
                            <button type="button" data-anular="{{ route('compras.anular', $c) }}" data-numero="{{ $c->numero_completo }}" class="flex items-center gap-1.5 text-red-500"><x-icon name="x-circle" class="size-3.5" /> Anular</button>
                        @else
                            <span aria-disabled="true" class="flex items-center gap-1.5 text-slate-300"><x-icon name="x-circle" class="size-3.5" /> Anular</span>
                        @endif
                    </div>
                </li>
            @endforeach
        </ul>

        <div class="border-t border-slate-200 px-4 py-3 sm:px-5">
            {{ $compras->links('vendor.pagination.ricopet') }}
        </div>
    @endif
</section>
