{{-- $sesion, $movimientos (MovimientoCaja), $pagos (Pago con venta/compra) --}}
@php
    // Una sola línea de tiempo: cobros/pagos de documentos + movimientos manuales
    $lineas = collect($pagos)->map(fn ($p) => (object) [
        'fecha'    => $p->fecha_pago,
        'ingreso'  => $p->tipo === 'cobro',
        'concepto' => $p->venta ? 'Venta ' . $p->venta->numero_completo : ($p->compra ? 'Compra ' . $p->compra->numero_completo : ucfirst($p->tipo)),
        'url'      => $p->venta ? route('ventas.show', $p->venta_id) : ($p->compra ? route('compras.show', $p->compra_id) : null),
        'metodo'   => $p->metodo_pago,
        'monto'    => $p->monto,
        'anulado'  => $p->estado === 'anulado',
        'manual'   => null,
    ])->concat(collect($movimientos)->map(fn ($m) => (object) [
        'fecha'    => $m->fecha_movimiento,
        'ingreso'  => $m->esIngreso(),
        'concepto' => $m->concepto,
        'url'      => null,
        'metodo'   => $m->metodo_pago,
        'monto'    => $m->monto,
        'anulado'  => $m->esAnulado(),
        'manual'   => $m,
    ]))->sortByDesc('fecha')->values();
    $metodos = config('ventas.metodos_pago') + config('compras.metodos_pago');
@endphp

<x-card title="Movimientos del turno" icon="arrow-left-right" subtitle="Cobros, pagos, ingresos y egresos">
    @if ($lineas->isEmpty())
        <x-empty-state icon="wallet" title="Sin movimientos todavía" description="Los cobros de ventas y los movimientos manuales aparecerán aquí." class="py-6" />
    @else
        <ul class="divide-y divide-slate-100">
            @foreach ($lineas as $l)
                <li @class(['flex items-center gap-3 py-2.5 text-sm', 'opacity-50' => $l->anulado])>
                    <span @class(['grid size-8 shrink-0 place-items-center rounded-md', 'bg-emerald-50 text-emerald-600' => $l->ingreso, 'bg-red-50 text-red-500' => ! $l->ingreso])>
                        <x-icon :name="$l->ingreso ? 'trending-up' : 'trending-down'" />
                    </span>
                    <div class="min-w-0 flex-1 leading-tight">
                        @if ($l->url)
                            <a href="{{ $l->url }}" class="block truncate font-medium hover:text-brand-600">{{ $l->concepto }}</a>
                        @else
                            <p class="truncate font-medium">{{ $l->concepto }}</p>
                        @endif
                        <p class="mt-0.5 text-[11px] text-slate-500">
                            {{ $l->fecha?->format('H:i') }} · {{ $metodos[$l->metodo] ?? ucfirst((string) $l->metodo) }}
                            @if ($l->anulado) · <span class="text-red-600">Anulado</span> @endif
                        </p>
                    </div>
                    <span @class(['shrink-0 font-semibold', 'text-emerald-700' => $l->ingreso, 'text-red-600' => ! $l->ingreso, 'line-through' => $l->anulado])>
                        {{ $l->ingreso ? '+' : '−' }} <x-money :value="$l->monto" />
                    </span>
                    @if ($l->manual && ! $l->anulado && $sesion->estaAbierta())
                        <form method="POST" action="{{ route('caja.movimientos.anular', $l->manual) }}" onsubmit="return confirm('¿Anular este movimiento?')">
                            @csrf @method('PATCH')
                            <button type="submit" title="Anular" aria-label="Anular {{ $l->concepto }}" class="grid size-7 place-items-center rounded text-slate-400 hover:bg-red-50 hover:text-red-600"><x-icon name="x" class="size-3.5" /></button>
                        </form>
                    @endif
                </li>
            @endforeach
        </ul>
    @endif
</x-card>
