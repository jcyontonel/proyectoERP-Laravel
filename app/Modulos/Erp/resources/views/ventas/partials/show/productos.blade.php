@php
    $n    = $venta->detalles->count();
    $cant = fn ($x) => rtrim(rtrim(number_format($x, 2, '.', ''), '0'), '.');
    $importe = fn ($d) => round($d->cantidad * $d->precio_unitario - $d->descuento, 2);
@endphp

<x-card title="Productos" icon="package" :class="$class ?? ''">
    <x-slot:aside>{{ $n }} {{ $n === 1 ? 'producto' : 'productos' }}</x-slot:aside>

    {{-- Tabla (≥ sm) --}}
    <div class="-mx-4 hidden overflow-x-auto sm:-mx-5 sm:block">
        <table class="w-full text-sm">
            <thead class="border-y border-slate-200 bg-slate-50/70 text-left text-xs font-medium text-slate-500">
                <tr>
                    <th class="py-2.5 pl-4 pr-3 sm:pl-5">Producto</th>
                    <th class="px-3 py-2.5 text-right">Cant.</th>
                    <th class="px-3 py-2.5 text-right">Precio unit.</th>
                    <th class="px-3 py-2.5 text-right">Desc.</th>
                    <th class="py-2.5 pl-3 pr-4 text-right sm:pr-5">Importe</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @foreach ($venta->detalles as $d)
                    <tr>
                        <td class="py-3 pl-4 pr-3 sm:pl-5">
                            <p class="font-medium">{{ $d->descripcion }}</p>
                            <p class="mt-0.5 text-xs text-slate-500">
                                {{ collect([$d->producto?->codigo, $d->tipoUnidad?->descripcion])->filter()->implode(' · ') ?: '—' }}
                            </p>
                        </td>
                        <td class="px-3 py-3 text-right">{{ $cant($d->cantidad) }}</td>
                        <td class="px-3 py-3 text-right"><x-money :value="$d->precio_unitario" /></td>
                        <td class="px-3 py-3 text-right text-slate-500">
                            @if ($d->descuento > 0) − <x-money :value="$d->descuento" /> @else — @endif
                        </td>
                        <td class="py-3 pl-3 pr-4 text-right font-semibold sm:pr-5"><x-money :value="$importe($d)" /></td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    {{-- Lista (< sm) --}}
    <ul class="divide-y divide-slate-100 sm:hidden">
        @foreach ($venta->detalles as $d)
            <li class="py-3">
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        <p class="text-sm font-semibold">{{ $d->descripcion }}</p>
                        <p class="mt-0.5 text-xs text-slate-500">
                            {{ $cant($d->cantidad) }} × <x-money :value="$d->precio_unitario" />
                            @if ($d->descuento > 0) · desc. <x-money :value="$d->descuento" /> @endif
                        </p>
                    </div>
                    <p class="shrink-0 text-sm font-bold"><x-money :value="$importe($d)" /></p>
                </div>
            </li>
        @endforeach
    </ul>

    <p class="mt-1 flex items-start gap-2 border-t border-slate-200 pt-3 text-xs text-slate-500">
        <x-icon name="info" class="mt-px size-3.5" />
        Los importes {{ $venta->empresa->precios_incluyen_igv ? 'incluyen' : 'no incluyen' }} IGV.
    </p>
</x-card>
