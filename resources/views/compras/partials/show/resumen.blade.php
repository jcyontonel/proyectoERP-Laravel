<x-card title="Resumen de compra" icon="file-text">
    <dl class="space-y-3 text-sm">
        <div class="flex items-center justify-between">
            <dt class="text-slate-600">Base imponible</dt>
            <dd class="font-medium"><x-money :value="$compra->subtotal" /></dd>
        </div>
        <div class="flex items-center justify-between">
            <dt class="text-slate-600">IGV @if ($compra->total_impuestos <= 0) (no afecto) @else ({{ (float) $compra->empresa->igv }} %) @endif</dt>
            <dd class="font-medium"><x-money :value="$compra->total_impuestos" /></dd>
        </div>
    </dl>

    <div class="mt-4 rounded-lg border border-brand-100 bg-brand-50 p-4">
        <p class="text-xs font-medium text-brand-700">Total de la compra</p>
        <p @class(['mt-1 text-3xl font-bold tracking-tight text-brand-700', 'line-through opacity-60' => $compra->esAnulada()])><x-money :value="$compra->total" /></p>
        <p class="mt-1 text-[11px] text-slate-500">Moneda: {{ $compra->moneda }}</p>
    </div>

    @if ($compra->esRegistrada())
        <dl class="mt-4 space-y-2 border-t border-slate-200 pt-4 text-sm">
            <div class="flex items-center justify-between">
                <dt class="text-slate-600">Pagado</dt>
                <dd class="font-medium text-emerald-700"><x-money :value="$compra->montoPagado()" /></dd>
            </div>
            <div class="flex items-center justify-between">
                <dt class="text-slate-600">Saldo por pagar</dt>
                <dd @class(['font-bold', 'text-amber-700' => $compra->saldo() > 0, 'text-slate-400' => $compra->saldo() <= 0])><x-money :value="$compra->saldo()" /></dd>
            </div>
        </dl>
    @endif
</x-card>
