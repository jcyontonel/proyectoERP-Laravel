@php
    // $metodosPago: total, metodos [label, icono, pct, monto]. Un tono por segmento (de más a menos intenso)
    $tonos = ['bg-brand-600', 'bg-brand-500/60', 'bg-brand-200', 'bg-accent-500/70', 'bg-slate-300'];
@endphp

<x-card title="Métodos de pago" :subtitle="($fecha->isToday() ? 'Hoy' : $fecha->translatedFormat('d M')) . ' · Ventas completadas'">
    @if ($metodosPago['total'] <= 0)
        <p class="text-sm text-slate-500">Sin ventas completadas en el día.</p>
    @else
        <div class="flex h-2 gap-0.5 overflow-hidden rounded-full bg-slate-100" role="img" aria-label="Distribución por método de pago">
            @foreach ($metodosPago['metodos'] as $i => $m)
                <div class="{{ $tonos[$i] ?? 'bg-slate-300' }}" style="width: {{ $m['pct'] }}%"></div>
            @endforeach
        </div>

        <ul class="mt-4 space-y-3">
            @foreach ($metodosPago['metodos'] as $i => $m)
                <li class="flex items-center gap-3">
                    <span class="grid size-8 shrink-0 place-items-center rounded-md bg-slate-100 text-slate-500"><x-icon :name="$m['icono']" /></span>
                    <div class="min-w-0 flex-1 leading-tight">
                        <p class="text-sm font-medium">{{ $m['label'] }}</p>
                        <p class="mt-0.5 flex items-center gap-1.5 text-[11px] text-slate-500">
                            <span class="size-1.5 rounded-full {{ $tonos[$i] ?? 'bg-slate-300' }}"></span>{{ $m['pct'] }}% del total
                        </p>
                    </div>
                    <p class="text-sm font-semibold"><x-money :value="$m['monto']" /></p>
                </li>
            @endforeach
        </ul>

        <div class="mt-4 flex items-center justify-between border-t border-slate-200 pt-3 text-sm">
            <span class="text-slate-600">Total del día</span>
            <span class="font-bold"><x-money :value="$metodosPago['total']" /></span>
        </div>
    @endif
</x-card>
