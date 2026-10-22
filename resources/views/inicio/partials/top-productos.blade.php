@use('App\Support\Unidades')
{{-- $topProductos: id, nombre, unidad, cantidad, importe (últimos 30 días) --}}
<x-card title="Más vendidos" icon="trending-up" subtitle="Últimos 30 días, por importe">
    @if ($topProductos->isEmpty())
        <p class="text-sm text-slate-500">Aún no hay ventas en los últimos 30 días.</p>
    @else
        @php $max = max($topProductos->max('importe'), 1); @endphp
        <ol class="space-y-3">
            @foreach ($topProductos as $i => $p)
                <li>
                    <div class="flex items-center justify-between gap-3 text-sm">
                        <a href="{{ route('productos.show', $p->id) }}" class="flex min-w-0 items-center gap-2 hover:text-brand-600">
                            <span class="grid size-5 shrink-0 place-items-center rounded bg-slate-100 text-[10px] font-bold text-slate-500">{{ $i + 1 }}</span>
                            <span class="truncate font-medium">{{ $p->nombre }}</span>
                        </a>
                        <span class="shrink-0 font-semibold"><x-money :value="$p->importe" /></span>
                    </div>
                    <div class="mt-1.5 flex items-center gap-2">
                        <div class="h-1.5 flex-1 overflow-hidden rounded-full bg-slate-100">
                            <div class="h-full rounded-full bg-brand-500" style="width: {{ round($p->importe / $max * 100) }}%"></div>
                        </div>
                        <span class="shrink-0 text-[11px] text-slate-500">{{ Unidades::conUnidad($p->cantidad, $p->unidad) }}</span>
                    </div>
                </li>
            @endforeach
        </ol>
    @endif
</x-card>
