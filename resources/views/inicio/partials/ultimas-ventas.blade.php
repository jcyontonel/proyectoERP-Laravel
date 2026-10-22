@php
    $fechaCorta = fn ($f) => ($f->isToday() ? 'Hoy' : $f->translatedFormat('d M')) . ', ' . $f->format('H:i');
@endphp

<x-card title="Últimas ventas" :subtitle="$alcance === 'propio' ? 'Tus comprobantes más recientes' : 'Actividad reciente de la empresa'">
    <x-slot:aside>
        <a href="{{ route('ventas.index') }}" class="inline-flex items-center gap-1 font-semibold text-brand-600 hover:text-brand-700">
            Ver todas <x-icon name="arrow-right" class="size-3.5" />
        </a>
    </x-slot:aside>

    {{-- Tabla (≥ md) --}}
    <div class="-mx-4 hidden overflow-x-auto sm:-mx-5 md:block">
        <table class="w-full text-sm">
            <thead class="border-y border-slate-200 text-left text-xs font-medium text-slate-500">
                <tr>
                    <th class="py-2.5 pl-4 pr-3 sm:pl-5">N° Comprobante</th>
                    <th class="px-3 py-2.5">Cliente</th>
                    <th class="px-3 py-2.5 text-right">Total</th>
                    <th class="px-3 py-2.5">Método de Pago</th>
                    <th class="px-3 py-2.5">Estado</th>
                    <th class="py-2.5 pl-3 pr-4 text-center sm:pr-5">Acciones</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($ultimasVentas as $v)
                    <tr>
                        <td class="py-3 pl-4 pr-3 sm:pl-5">
                            <p class="font-semibold">{{ $v->numero }}</p>
                            <p class="text-xs text-slate-500">{{ $fechaCorta($v->fecha) }}</p>
                        </td>
                        <td class="px-3 py-3">
                            <p class="font-medium">{{ $v->cliente_nombre }}</p>
                            <p class="text-xs text-slate-500">{{ $v->cliente_doc }}</p>
                        </td>
                        <td class="px-3 py-3 text-right font-semibold"><x-money :value="$v->total" /></td>
                        <td class="px-3 py-3"><x-metodo-pago :metodo="$v->metodo" :label="$v->metodo_label" /></td>
                        <td class="px-3 py-3"><x-estado-venta :estado="$v->estado" /></td>
                        <td class="py-3 pl-3 pr-4 text-center sm:pr-5">
                            @if ($v->estado === 'borrador')
                                <a href="{{ route('ventas.edit', $v->id) }}" title="Editar borrador" aria-label="Editar {{ $v->numero }}" class="inline-grid size-8 place-items-center rounded-md text-brand-600 hover:bg-brand-50"><x-icon name="pencil" /></a>
                            @else
                                <a href="{{ route('ventas.show', $v->id) }}" title="Ver detalle" aria-label="Ver {{ $v->numero }}" class="inline-grid size-8 place-items-center rounded-md text-brand-600 hover:bg-brand-50"><x-icon name="eye" /></a>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="py-8 text-center text-sm text-slate-500">Aún no hay ventas registradas.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- Lista compacta (< md) --}}
    <ul class="divide-y divide-slate-100 md:hidden">
        @forelse ($ultimasVentas as $v)
            <li class="py-3">
                <div class="flex items-center justify-between gap-2">
                    <a href="{{ $v->estado === 'borrador' ? route('ventas.edit', $v->id) : route('ventas.show', $v->id) }}" class="text-sm font-semibold text-brand-600">{{ $v->numero }}</a>
                    <x-estado-venta :estado="$v->estado" />
                </div>
                <div class="mt-1.5 flex items-end justify-between gap-3">
                    <div class="min-w-0">
                        <p class="truncate text-sm font-medium">{{ $v->cliente_nombre }}</p>
                        <p class="text-xs text-slate-500">{{ $fechaCorta($v->fecha) }} · <x-metodo-pago :metodo="$v->metodo" :label="$v->metodo_label" class="align-middle" /></p>
                    </div>
                    <p class="text-sm font-bold"><x-money :value="$v->total" /></p>
                </div>
            </li>
        @empty
            <li class="py-8 text-center text-sm text-slate-500">Aún no hay ventas registradas.</li>
        @endforelse
    </ul>

    <p class="mt-1 flex items-start gap-2 border-t border-slate-200 pt-3 text-xs text-slate-500">
        <x-icon name="info" class="mt-px size-3.5" />
        <span>Mostrando las {{ count($ultimasVentas) }} últimas · {{ $kpiVentas['cantidad'] }} completadas el día consultado · Los borradores no suman al total.</span>
    </p>
</x-card>