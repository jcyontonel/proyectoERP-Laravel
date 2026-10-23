{{-- $utilidad: mes, neto, costo, utilidad, margen, igv --}}
<x-card title="Rentabilidad del mes" icon="pie-chart" :subtitle="$utilidad['mes'] . ' · Importes sin IGV'">
    <x-slot:aside>
        <a href="{{ route('reportes.utilidad') }}" class="inline-flex items-center gap-1 font-semibold text-brand-600 hover:text-brand-700">Reporte <x-icon name="arrow-right" class="size-3.5" /></a>
    </x-slot:aside>

    <div class="grid grid-cols-2 gap-4 sm:grid-cols-4">
        <div>
            <p class="text-xs text-slate-500">Ventas netas</p>
            <p class="mt-1 text-lg font-bold"><x-money :value="$utilidad['neto']" /></p>
        </div>
        <div>
            <p class="text-xs text-slate-500">Costo de lo vendido</p>
            <p class="mt-1 text-lg font-bold text-slate-600"><x-money :value="$utilidad['costo']" /></p>
        </div>
        <div>
            <p class="text-xs text-slate-500">Utilidad bruta</p>
            <p @class(['mt-1 text-lg font-bold', 'text-emerald-700' => $utilidad['utilidad'] >= 0, 'text-red-600' => $utilidad['utilidad'] < 0])><x-money :value="$utilidad['utilidad']" /></p>
        </div>
        <div>
            <p class="text-xs text-slate-500">IGV de ventas</p>
            <p class="mt-1 text-lg font-bold text-slate-600"><x-money :value="$utilidad['igv']" /></p>
        </div>
    </div>

    @if ($utilidad['margen'] !== null)
        <div class="mt-4">
            <div class="flex items-center justify-between text-xs text-slate-500">
                <span>Margen bruto</span>
                <span class="font-semibold text-ink">{{ $utilidad['margen'] }}%</span>
            </div>
            <div class="mt-1.5 h-2 overflow-hidden rounded-full bg-slate-100">
                <div @class(['h-full rounded-full', 'bg-emerald-500' => $utilidad['margen'] >= 0, 'bg-red-500' => $utilidad['margen'] < 0]) style="width: {{ min(abs($utilidad['margen']), 100) }}%"></div>
            </div>
        </div>
    @endif
    <p class="mt-3 text-[11px] text-slate-500">El costo es el registrado al vender (o el costo actual del producto en ventas anteriores a ese registro).</p>
</x-card>
