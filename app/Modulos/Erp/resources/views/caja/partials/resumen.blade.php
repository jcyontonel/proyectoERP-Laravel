{{-- $sesion, $resumen (CajaService::resumen) --}}
<x-card title="Resumen del turno" icon="file-text" :subtitle="$sesion->caja?->nombre . ' · ' . $sesion->usuario?->nombre_completo">
    <dl class="divide-y divide-slate-100 text-sm">
        <x-dato label="Monto de apertura"><x-money :value="$resumen['apertura']" /></x-dato>
        <x-dato label="Ventas registradas">{{ $resumen['ventas'] }} · <x-money :value="$resumen['ventas_total']" /></x-dato>
        @if ($resumen['ventas_anuladas'])
            <x-dato label="Ventas anuladas"><span class="text-red-600">{{ $resumen['ventas_anuladas'] }}</span></x-dato>
        @endif
    </dl>

    <p class="mt-4 text-xs font-semibold uppercase tracking-wide text-slate-400">Cobros por método</p>
    <ul class="mt-2 space-y-1.5 text-sm">
        @forelse ($resumen['cobros'] as $c)
            <li class="flex items-center justify-between gap-3">
                <x-metodo-pago :metodo="$c['metodo']" :label="$c['label'] . ' (' . $c['cantidad'] . ')'" />
                <span class="font-medium"><x-money :value="$c['monto']" /></span>
            </li>
        @empty
            <li class="text-xs text-slate-500">Aún no hay cobros en este turno.</li>
        @endforelse
    </ul>

    <dl class="mt-4 divide-y divide-slate-100 border-t border-slate-200 pt-1 text-sm">
        <x-dato label="Total cobrado"><x-money :value="$resumen['total_cobros']" /></x-dato>
        @if ($resumen['total_pagos'] > 0)
            <x-dato label="Pagos a proveedores"><span class="text-red-600">− <x-money :value="$resumen['total_pagos']" /></span></x-dato>
        @endif
        <x-dato label="Otros ingresos"><span class="text-emerald-700">+ <x-money :value="$resumen['ingresos']" /></span></x-dato>
        <x-dato label="Egresos"><span class="text-red-600">− <x-money :value="$resumen['egresos']" /></span></x-dato>
    </dl>

    <div class="mt-3 rounded-lg border border-brand-100 bg-brand-50 p-4">
        <p class="text-xs font-medium text-brand-700">{{ $sesion->estaAbierta() ? 'Efectivo esperado en caja' : 'Efectivo esperado al cierre' }}</p>
        <p class="mt-1 text-2xl font-bold tracking-tight text-brand-700"><x-money :value="$resumen['efectivo']" /></p>
        <p class="mt-1 text-[11px] text-slate-500">Apertura + cobros y otros ingresos en efectivo − pagos y egresos en efectivo</p>
    </div>

    @unless ($sesion->estaAbierta())
        <dl class="mt-3 divide-y divide-slate-100 text-sm">
            <x-dato label="Efectivo contado"><x-money :value="$sesion->monto_contado" /></x-dato>
            <x-dato label="Diferencia">
                <span @class(['font-semibold', 'text-emerald-700' => abs($sesion->diferencia) < 0.005, 'text-amber-700' => $sesion->diferencia > 0.004, 'text-red-600' => $sesion->diferencia < -0.004])>
                    {{ abs($sesion->diferencia) < 0.005 ? 'Cuadra' : ($sesion->diferencia > 0 ? 'Sobrante ' : 'Faltante ') . 'S/ ' . number_format(abs($sesion->diferencia), 2) }}
                </span>
            </x-dato>
        </dl>
    @endunless
</x-card>
