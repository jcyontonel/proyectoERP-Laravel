@php
    $n    = $compra->detalles->count();
    $cant = fn ($x) => rtrim(rtrim(number_format($x, 2, '.', ''), '0'), '.');
    $info = fn ($d) => collect([
        $d->producto?->codigo,
        $d->tipoUnidad?->descripcion,
        $d->lote ? 'Lote ' . $d->lote->codigo_lote : null,
        $d->lote?->fecha_vencimiento ? 'Vence ' . $d->lote->fecha_vencimiento->format('d/m/Y') : null,
    ])->filter()->implode(' · ');
@endphp

<x-card title="Productos comprados" icon="package" :class="$class ?? ''">
    <x-slot:aside>{{ $n }} {{ $n === 1 ? 'línea' : 'líneas' }}</x-slot:aside>

    {{-- Tabla (≥ sm) --}}
    <div class="-mx-4 hidden overflow-x-auto sm:-mx-5 sm:block">
        <table class="w-full text-sm">
            <thead class="border-y border-slate-200 bg-slate-50/70 text-left text-xs font-medium text-slate-500">
                <tr>
                    <th class="py-2.5 pl-4 pr-3 sm:pl-5">Producto</th>
                    <th class="px-3 py-2.5 text-right">Cant.</th>
                    <th class="px-3 py-2.5 text-right">Costo unit.</th>
                    <th class="px-3 py-2.5 text-right">IGV</th>
                    <th class="py-2.5 pl-3 pr-4 text-right sm:pr-5">Importe</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @foreach ($compra->detalles as $d)
                    <tr>
                        <td class="py-3 pl-4 pr-3 sm:pl-5">
                            <p class="font-medium">{{ $d->descripcion }}</p>
                            <p class="mt-0.5 text-xs text-slate-500">{{ $info($d) ?: '—' }}</p>
                        </td>
                        <td class="px-3 py-3 text-right">{{ $cant($d->cantidad) }}</td>
                        <td class="px-3 py-3 text-right"><x-money :value="$d->precio_unitario" /></td>
                        <td class="px-3 py-3 text-right text-slate-500"><x-money :value="$d->impuesto" /></td>
                        <td class="py-3 pl-3 pr-4 text-right font-semibold sm:pr-5"><x-money :value="$d->total" /></td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    {{-- Lista (< sm) --}}
    <ul class="divide-y divide-slate-100 sm:hidden">
        @foreach ($compra->detalles as $d)
            <li class="py-3">
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        <p class="text-sm font-semibold">{{ $d->descripcion }}</p>
                        <p class="mt-0.5 text-xs text-slate-500">{{ $cant($d->cantidad) }} × <x-money :value="$d->precio_unitario" /></p>
                        @if ($info($d)) <p class="text-xs text-slate-400">{{ $info($d) }}</p> @endif
                    </div>
                    <p class="shrink-0 text-sm font-bold"><x-money :value="$d->total" /></p>
                </div>
            </li>
        @endforeach
    </ul>

    <p class="mt-1 flex items-start gap-2 border-t border-slate-200 pt-3 text-xs text-slate-500">
        <x-icon name="info" class="mt-px size-3.5" />
        El importe de cada línea incluye su IGV. El costo unitario es el valor de compra sin IGV.
    </p>
</x-card>
