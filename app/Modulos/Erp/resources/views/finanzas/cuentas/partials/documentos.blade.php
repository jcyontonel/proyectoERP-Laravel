{{-- Documentos pendientes ($filas: Venta|Compra con 'pagado'), $c y $ruta del index o del estado de cuenta --}}
@php $rel = $c['relacion']; @endphp

{{-- Tabla (≥ lg) --}}
<div class="hidden overflow-x-auto lg:block">
    <table class="w-full text-sm">
        <thead class="border-y border-slate-200 bg-slate-50/70 text-left text-xs font-medium text-slate-500">
            <tr>
                <th class="py-3 pl-5 pr-3">{{ $c['documento_label'] }}</th>
                @unless ($sinTercero ?? false)<th class="px-3 py-3">{{ $c['tercero'] }}</th>@endunless
                <th class="px-3 py-3">Emisión</th>
                <th class="px-3 py-3">Vencimiento</th>
                <th class="px-3 py-3 text-right">Total</th>
                <th class="px-3 py-3 text-right">{{ $c['modo'] === 'cobrar' ? 'Cobrado' : 'Pagado' }}</th>
                <th class="px-3 py-3 text-right">Saldo</th>
                <th class="py-3 pl-3 pr-5 text-center">Acciones</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
            @foreach ($filas as $d)
                @php $t = $d->{$rel}; $atraso = $d->diasVencido(); @endphp
                <tr class="hover:bg-slate-50/60">
                    <td class="py-3 pl-5 pr-3">
                        <a href="{{ route($c['ruta_doc'], $d) }}" class="font-semibold text-brand-600 hover:text-brand-700">{{ $d->numero_completo }}</a>
                        @if ($d->condicion_pago === 'credito')<p class="text-xs text-slate-500">Crédito</p>@endif
                    </td>
                    @unless ($sinTercero ?? false)
                        <td class="px-3 py-3">
                            @if ($t)
                                <a href="{{ $ruta('cuenta', $t) }}" class="font-medium hover:text-brand-600">{{ ($c['nombre'])($t) }}</a>
                                <p class="text-xs text-slate-500">{{ ($c['documento'])($t) }}</p>
                            @else
                                <span class="text-slate-400">—</span>
                            @endif
                        </td>
                    @endunless
                    <td class="whitespace-nowrap px-3 py-3">{{ $d->fecha_emision->translatedFormat('d M Y') }}</td>
                    <td class="whitespace-nowrap px-3 py-3">
                        @if ($d->fecha_vencimiento)
                            {{ $d->fecha_vencimiento->translatedFormat('d M Y') }}
                            @if ($atraso > 0)
                                <x-badge tone="danger" shape="box" class="mt-0.5">{{ $atraso }} {{ $atraso === 1 ? 'día' : 'días' }} de atraso</x-badge>
                            @elseif ($d->fecha_vencimiento->isToday())
                                <x-badge tone="warning" shape="box" class="mt-0.5">Vence hoy</x-badge>
                            @elseif ($d->fecha_vencimiento->lte(today()->addDays(7)))
                                <x-badge tone="warning" shape="box" class="mt-0.5">En {{ (int) today()->diffInDays($d->fecha_vencimiento) }} días</x-badge>
                            @endif
                        @else
                            <span class="text-xs text-slate-400">Sin fecha</span>
                        @endif
                    </td>
                    <td class="px-3 py-3 text-right"><x-money :value="$d->total" /></td>
                    <td class="px-3 py-3 text-right text-slate-600"><x-money :value="$d->montoPagado()" /></td>
                    <td class="px-3 py-3 text-right font-semibold"><x-money :value="$d->saldo()" /></td>
                    <td class="py-3 pl-3 pr-5">
                        <div class="flex items-center justify-center gap-1">
                            <button type="button" class="btn btn-secondary !h-8 !px-2.5 text-xs"
                                data-pagar="{{ $ruta('registrar', $d) }}" data-titulo="{{ $c['verbo'] }} {{ $d->numero_completo }}"
                                data-detalle="{{ $t ? ($c['nombre'])($t) : $d->numero_completo }}" data-saldo="{{ number_format($d->saldo(), 2, '.', '') }}">
                                <x-icon name="coins" class="text-brand-600" /> {{ $c['verbo'] }}
                            </button>
                            <a href="{{ route($c['ruta_doc'], $d) }}" title="Ver documento" aria-label="Ver {{ $d->numero_completo }}" class="grid size-8 place-items-center rounded-md text-brand-600 hover:bg-brand-50"><x-icon name="eye" /></a>
                        </div>
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>

{{-- Tarjetas (< lg) --}}
<ul class="grid gap-3 border-t border-slate-200 p-4 sm:grid-cols-2 sm:p-5 lg:hidden">
    @foreach ($filas as $d)
        @php $t = $d->{$rel}; $atraso = $d->diasVencido(); @endphp
        <li class="rounded-lg border border-slate-200 p-4">
            <div class="flex items-center justify-between gap-2">
                <a href="{{ route($c['ruta_doc'], $d) }}" class="text-sm font-bold text-brand-600">{{ $d->numero_completo }}</a>
                @if ($atraso > 0)
                    <x-badge tone="danger" shape="box">{{ $atraso }} d. de atraso</x-badge>
                @else
                    <x-badge tone="success" shape="box">Al día</x-badge>
                @endif
            </div>
            @if ($t && ! ($sinTercero ?? false))
                <a href="{{ $ruta('cuenta', $t) }}" class="mt-2 block truncate text-sm font-semibold">{{ ($c['nombre'])($t) }}</a>
            @endif
            <p class="mt-1 text-xs text-slate-500">
                Emitido {{ $d->fecha_emision->translatedFormat('d M Y') }}
                @if ($d->fecha_vencimiento) · vence {{ $d->fecha_vencimiento->translatedFormat('d M Y') }} @endif
            </p>
            <div class="mt-3 flex items-end justify-between gap-3">
                <p class="text-xs text-slate-500">Total <x-money :value="$d->total" /></p>
                <div class="text-right">
                    <p class="text-[11px] text-slate-500">Saldo</p>
                    <p class="text-lg font-bold"><x-money :value="$d->saldo()" /></p>
                </div>
            </div>
            <button type="button" class="btn btn-secondary mt-3 w-full"
                data-pagar="{{ $ruta('registrar', $d) }}" data-titulo="{{ $c['verbo'] }} {{ $d->numero_completo }}"
                data-detalle="{{ $t ? ($c['nombre'])($t) : $d->numero_completo }}" data-saldo="{{ number_format($d->saldo(), 2, '.', '') }}">
                <x-icon name="coins" class="text-brand-600" /> {{ $c['verbo'] }}
            </button>
        </li>
    @endforeach
</ul>
