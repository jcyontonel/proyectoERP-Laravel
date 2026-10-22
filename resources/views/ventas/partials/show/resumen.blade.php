@php
    $bruto  = round($venta->detalles->sum(fn ($d) => $d->cantidad * $d->precio_unitario), 2);
    $igvPct = (float) $venta->empresa->igv;
@endphp

<aside class="{{ $class ?? '' }}">
    <x-card title="Resumen de venta" icon="file-text">
        <dl class="space-y-3 text-sm">
            <div class="flex items-center justify-between">
                <dt class="text-slate-600">Subtotal</dt>
                <dd class="font-medium"><x-money :value="$bruto" /></dd>
            </div>
            <div class="flex items-center justify-between text-emerald-700">
                <dt>Descuento aplicado ({{ rtrim(rtrim(number_format($venta->descuento_porcentaje, 2, '.', ''), '0'), '.') ?: '0' }} %)</dt>
                <dd class="font-medium">− <x-money :value="$venta->descuento" /></dd>
            </div>
            <div class="space-y-3 border-t border-slate-200 pt-3">
                <div class="flex items-center justify-between">
                    <dt class="text-slate-600">Base imponible</dt>
                    <dd class="font-medium"><x-money :value="$venta->subtotal" /></dd>
                </div>
                <div class="flex items-center justify-between">
                    <dt class="text-slate-600">IGV ({{ $igvPct }} %)</dt>
                    <dd class="font-medium"><x-money :value="$venta->total_impuestos" /></dd>
                </div>
            </div>
        </dl>

        <div class="mt-4 rounded-lg border border-brand-100 bg-brand-50 p-4">
            <p class="text-xs font-medium text-brand-700">Total {{ $venta->esAnulada() ? 'anulado' : ($venta->esCredito() ? 'al crédito' : 'pagado') }}</p>
            <p @class(['mt-1 text-3xl font-bold tracking-tight text-brand-700', 'line-through opacity-60' => $venta->esAnulada()])><x-money :value="$venta->total" /></p>
            <p class="mt-1 text-[11px] text-slate-500">Moneda: soles (PEN) · Incluye IGV · {{ $venta->metodo_label }}</p>
        </div>
    </x-card>

    @if ($venta->esCredito() && ! $venta->esBorrador())
        @include('ventas.partials.show.cobros')
    @endif
</aside>
