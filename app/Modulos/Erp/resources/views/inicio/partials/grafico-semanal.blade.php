@php
    // $grafico: dias [label, total, hoy], total, actual, semanas
    $semana  = $grafico['dias'];
    $maxDato = max(collect($semana)->max('total'), 1);
    // Escala del eje Y (≈ 4 divisiones, paso redondeado a 1-2-5 × 10^n)
    $bruto = $maxDato / 4;
    $mag   = 10 ** floor(log10($bruto));
    $paso  = collect([1, 2, 5, 10])->map(fn ($m) => $m * $mag)->first(fn ($p) => $p >= $bruto);
    $techo = (floor($maxDato / $paso) + 1) * $paso;
    $ticks = range(0, $techo, $paso);
    $hoyTotal = collect($semana)->firstWhere('hoy', true)['total'] ?? null;
@endphp

<x-card title="Ventas de la semana" :subtitle="($alcance === 'propio' ? 'Tus ventas' : 'Ventas completadas') . ' por día · ' . ($empresaActual?->moneda ?? 'PEN')">
    <x-slot:aside>
        <form method="GET" action="{{ route('inicio') }}" class="relative">
            <input type="hidden" name="fecha" value="{{ $fecha->toDateString() }}">
            <label for="semana" class="sr-only">Semana</label>
            <select id="semana" name="semana" onchange="this.form.submit()" class="field h-9 appearance-none pr-8 text-xs">
                @foreach ($grafico['semanas'] as $valor => $etiqueta)
                    <option value="{{ $valor }}" @selected($valor === $grafico['actual'])>{{ $etiqueta }}</option>
                @endforeach
            </select>
            <x-icon name="chevron-down" class="pointer-events-none absolute right-2.5 top-2.5 size-3.5 text-slate-400" />
        </form>
    </x-slot:aside>

    @if ($grafico['total'] <= 0)
        <x-empty-state icon="bar-chart" title="Sin ventas en esta semana" description="Cuando registres ventas verás aquí el total de cada día." class="py-10" />
    @else
        <div class="flex gap-3">
            {{-- Eje Y --}}
            <div class="flex h-52 flex-col-reverse justify-between text-right text-[11px] leading-none text-slate-400" aria-hidden="true">
                @foreach ($ticks as $t)
                    <span>{{ number_format($t) }}</span>
                @endforeach
            </div>

            <div class="min-w-0 flex-1">
                <div class="relative h-52">
                    <div class="absolute inset-0 flex flex-col-reverse justify-between" aria-hidden="true">
                        @foreach ($ticks as $t)
                            <div class="border-t border-slate-100"></div>
                        @endforeach
                    </div>

                    <ul class="relative flex h-full items-end gap-2 sm:gap-4">
                        @foreach ($semana as $dia)
                            <li class="flex h-full min-w-0 flex-1 flex-col items-center justify-end" title="{{ $dia['label'] }}: S/ {{ number_format($dia['total'], 2) }}">
                                <span @class([
                                    'mb-1 text-[10px] tracking-tight sm:text-[11px]',
                                    'font-semibold text-brand-700' => $dia['hoy'],
                                    'text-slate-500' => ! $dia['hoy'],
                                ])>{{ $dia['total'] > 0 ? number_format($dia['total'], fmod($dia['total'], 1) == 0 ? 0 : 2) : '' }}</span>
                                <div @class([
                                    'w-full max-w-14 rounded-t-md transition-all',
                                    'bg-brand-600' => $dia['hoy'],
                                    'bg-brand-200' => ! $dia['hoy'],
                                ]) style="height: {{ round($dia['total'] / $techo * 100, 1) }}%"></div>
                            </li>
                        @endforeach
                    </ul>
                </div>

                <ol class="mt-2 flex gap-2 sm:gap-4">
                    @foreach ($semana as $dia)
                        <li @class([
                            'min-w-0 flex-1 text-center text-[10px] leading-tight sm:text-[11px]',
                            'font-semibold text-brand-700' => $dia['hoy'],
                            'text-slate-500' => ! $dia['hoy'],
                        ])>{{ $dia['label'] }}</li>
                    @endforeach
                </ol>
            </div>
        </div>
    @endif

    <div class="mt-5 flex flex-wrap items-center justify-between gap-2 border-t border-slate-200 pt-3 text-xs text-slate-500">
        <span>Total de la semana: <strong class="text-ink">S/ {{ number_format($grafico['total'], 2) }}</strong></span>
        @if ($hoyTotal !== null)
            <span class="flex items-center gap-1.5 font-medium text-brand-700">
                <span class="size-2 rounded-sm bg-brand-600"></span> Hoy: S/ {{ number_format($hoyTotal, 2) }}
            </span>
        @endif
    </div>
</x-card>
