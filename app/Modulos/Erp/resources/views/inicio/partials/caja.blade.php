{{-- $caja: abierta, caja, desde, apertura, efectivo --}}
<x-card title="Mi turno de caja" icon="wallet">
    <x-slot:aside>
        <x-badge :tone="$caja['abierta'] ? 'success' : 'neutral'" :dot="true">{{ $caja['abierta'] ? 'Abierta' : 'Cerrada' }}</x-badge>
    </x-slot:aside>

    @if ($caja['abierta'])
        <dl class="divide-y divide-slate-100">
            <x-dato label="Caja">{{ $caja['caja'] }}</x-dato>
            <x-dato label="Apertura">{{ $caja['desde']->format('d/m H:i') }}</x-dato>
            <x-dato label="Monto inicial"><x-money :value="$caja['apertura']" /></x-dato>
            <x-dato label="Efectivo esperado"><span class="text-base font-bold"><x-money :value="$caja['efectivo']" /></span></x-dato>
        </dl>
        <a href="{{ route('caja.cierre') }}" class="btn btn-secondary mt-3 w-full"><x-icon name="lock" class="text-brand-600" /> Cerrar turno y arquear</a>
    @else
        <p class="text-sm text-slate-500">No tienes un turno abierto. Ábrelo con el monto inicial de efectivo para registrar cobros, gastos y el arqueo al cierre.</p>
        <a href="{{ route('caja.index') }}" class="btn btn-primary mt-4 w-full"><x-icon name="wallet" /> Abrir caja</a>
    @endif
</x-card>
