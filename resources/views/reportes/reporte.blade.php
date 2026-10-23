{{--
    Vista genérica de reportes (ReporteController@show)
      $clave, $titulo, $descripcion, $grupo, $filtros (fechas|agrupar|almacen|categoria|vendedor|dias)
      $datos   ReporteService: resumen, columnas, filas, totales, grafico
      $opciones almacenes, categorias, vendedores
--}}
@extends('layouts.app')

@use('App\Modulos\Catalogo\Support\Unidades')

@php
    $tonos = ['brand' => 'brand', 'emerald' => 'emerald', 'amber' => 'amber', 'red' => 'red', 'slate' => 'slate'];
    $formato = function ($valor, string $tipo) {
        if ($valor === null || $valor === '') return '—';
        return match ($tipo) {
            'moneda'     => 'S/ ' . number_format((float) $valor, 2),
            'numero'     => number_format((float) $valor, fmod((float) $valor, 1) == 0 ? 0 : 1),
            'cantidad'   => Unidades::cantidad($valor),
            'porcentaje' => number_format((float) $valor, 1) . ' %',
            'fecha'      => \Illuminate\Support\Carbon::parse($valor)->format('d/m/Y'),
            default      => $valor,
        };
    };
    $alineado = fn (string $tipo) => in_array($tipo, ['moneda', 'numero', 'cantidad', 'porcentaje'], true) ? 'text-right' : 'text-left';
    $grafico = $datos['grafico'] ?? null;
    $maxGrafico = $grafico ? max(collect($grafico['valores'])->map(fn ($v) => abs((float) $v))->max() ?? 0, 1) : 1;
    $desde = request('desde', now()->startOfMonth()->toDateString());
    $hasta = request('hasta', now()->toDateString());
@endphp

@section('title', $titulo)

@section('content')
    <x-page-header :title="$titulo" :migas="['Reportes' => route('reportes.index'), $titulo => null]" :subtitle="$descripcion">
        <x-slot:acciones>
            <a href="{{ route('reportes.' . $clave . '.exportar', request()->query()) }}" class="btn btn-secondary"><x-icon name="download" class="text-brand-600" /> Exportar CSV</a>
            <button type="button" onclick="window.print()" class="btn btn-secondary"><x-icon name="printer" class="text-brand-600" /> Imprimir</button>
        </x-slot:acciones>
    </x-page-header>

    {{-- Filtros --}}
    @if ($filtros)
        <form method="GET" class="mt-6 flex flex-wrap items-end gap-4 rounded-xl border border-slate-200 bg-white p-4 shadow-sm print:hidden sm:p-5">
            @if (in_array('fechas', $filtros, true))
                <div class="w-40"><x-input name="desde" label="Desde" type="date" :value="$desde" /></div>
                <div class="w-40"><x-input name="hasta" label="Hasta" type="date" :value="$hasta" /></div>
            @endif
            @if (in_array('agrupar', $filtros, true))
                <div class="w-36">
                    <x-select name="agrupar" label="Agrupar por">
                        <option value="dia" @selected(request('agrupar', 'dia') === 'dia')>Día</option>
                        <option value="mes" @selected(request('agrupar') === 'mes')>Mes</option>
                    </x-select>
                </div>
            @endif
            @if (in_array('almacen', $filtros, true))
                <div class="w-48">
                    <x-select name="almacen_id" label="Almacén">
                        <option value="">Todos</option>
                        @foreach ($opciones['almacenes'] as $a)<option value="{{ $a->id }}" @selected(request('almacen_id') == $a->id)>{{ $a->nombre }}</option>@endforeach
                    </x-select>
                </div>
            @endif
            @if (in_array('categoria', $filtros, true))
                <div class="w-52">
                    <x-select name="categoria_id" label="Categoría">
                        <option value="">Todas</option>
                        @foreach ($opciones['categorias'] as $c)<option value="{{ $c->id }}" @selected(request('categoria_id') == $c->id)>{{ $c->nombre }}</option>@endforeach
                    </x-select>
                </div>
            @endif
            @if (in_array('vendedor', $filtros, true))
                <div class="w-52">
                    <x-select name="user_id" label="Vendedor">
                        <option value="">Todos</option>
                        @foreach ($opciones['vendedores'] as $u)<option value="{{ $u->id }}" @selected(request('user_id') == $u->id)>{{ $u->nombre_completo }}</option>@endforeach
                    </x-select>
                </div>
            @endif
            @if (in_array('dias', $filtros, true))
                <div class="w-40"><x-input name="dias" label="Días sin venta" type="number" min="1" :value="request('dias', 60)" /></div>
            @endif
            <button type="submit" class="btn btn-primary"><x-icon name="sliders" /> Generar</button>
            @if (in_array('fechas', $filtros, true))
                <div class="flex flex-wrap gap-1.5 text-xs">
                    @foreach (['Hoy' => [now(), now()], 'Este mes' => [now()->startOfMonth(), now()], 'Mes anterior' => [now()->subMonthNoOverflow()->startOfMonth(), now()->subMonthNoOverflow()->endOfMonth()], 'Este año' => [now()->startOfYear(), now()]] as $etiqueta => [$a, $b])
                        <a href="{{ request()->fullUrlWithQuery(['desde' => $a->toDateString(), 'hasta' => $b->toDateString()]) }}"
                            @class(['rounded-md border px-2.5 py-1.5 font-medium', 'border-brand-200 bg-brand-50 text-brand-700' => $desde === $a->toDateString() && $hasta === $b->toDateString(), 'border-slate-200 text-slate-600 hover:bg-slate-50' => ! ($desde === $a->toDateString() && $hasta === $b->toDateString())])>{{ $etiqueta }}</a>
                    @endforeach
                </div>
            @endif
        </form>
    @endif

    <p class="mt-4 hidden text-sm print:block">
        {{ $empresaActual?->razon_social }} · {{ in_array('fechas', $filtros, true) ? 'Del ' . \Illuminate\Support\Carbon::parse($desde)->format('d/m/Y') . ' al ' . \Illuminate\Support\Carbon::parse($hasta)->format('d/m/Y') : 'Al ' . now()->format('d/m/Y') }}
    </p>

    {{-- Indicadores --}}
    <section class="mt-6 grid grid-cols-2 gap-4 xl:grid-cols-4">
        @foreach ($datos['resumen'] as [$label, $valor, $tipo, $tono])
            <x-stat-card :title="$label" :icon="$tipo === 'moneda' ? 'dollar-sign' : ($tipo === 'porcentaje' ? 'percent' : 'hash')" :tone="$tonos[$tono] ?? 'brand'" :value="$formato($valor, $tipo)" />
        @endforeach
    </section>

    {{-- Gráfico de barras horizontales --}}
    @if ($grafico && count($grafico['valores']) > 0)
        <x-card title="Gráfico" icon="bar-chart" class="mt-5">
            <ul class="space-y-2">
                @foreach ($grafico['etiquetas'] as $i => $etiqueta)
                    @php $v = (float) $grafico['valores'][$i]; @endphp
                    <li class="grid grid-cols-[minmax(6rem,12rem)_minmax(0,1fr)_6.5rem] items-center gap-3 text-xs">
                        <span class="truncate text-slate-600" title="{{ $etiqueta }}">{{ $etiqueta }}</span>
                        <span class="h-3 overflow-hidden rounded-full bg-slate-100">
                            <span class="block h-full rounded-full {{ $v < 0 ? 'bg-red-400' : 'bg-brand-500' }}" style="width: {{ max(round(abs($v) / $maxGrafico * 100, 1), 1) }}%"></span>
                        </span>
                        <span class="text-right font-semibold">{{ $formato($v, $grafico['tipo']) }}</span>
                    </li>
                @endforeach
            </ul>
        </x-card>
    @endif

    {{-- Tabla --}}
    <section class="mt-5 overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
        @if ($datos['filas']->isEmpty())
            <x-empty-state icon="bar-chart" title="Sin datos para estos filtros" description="Prueba con otro rango de fechas o almacén." />
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="border-b border-slate-200 bg-slate-50/70 text-xs font-medium text-slate-500">
                        <tr>
                            @foreach ($datos['columnas'] as [$k, $label, $tipo])
                                <th class="whitespace-nowrap px-3 py-3 first:pl-5 last:pr-5 {{ $alineado($tipo) }}">{{ $label }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($datos['filas'] as $fila)
                            <tr class="hover:bg-slate-50/60">
                                @foreach ($datos['columnas'] as [$k, $label, $tipo])
                                    <td @class(['px-3 py-2.5 first:pl-5 last:pr-5', $alineado($tipo), 'text-red-600' => $tipo === 'moneda' && is_numeric($fila[$k] ?? null) && $fila[$k] < 0])>
                                        @if ($loop->first && ! empty($fila['_url']))
                                            <a href="{{ $fila['_url'] }}" class="font-medium text-brand-600 hover:text-brand-700">{{ $formato($fila[$k] ?? null, $tipo) }}</a>
                                        @else
                                            {{ $formato($fila[$k] ?? null, $tipo) }}
                                        @endif
                                    </td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                    @if (! empty($datos['totales']))
                        <tfoot class="border-t-2 border-slate-200 bg-slate-50 font-semibold">
                            <tr>
                                @foreach ($datos['columnas'] as [$k, $label, $tipo])
                                    <td class="px-3 py-3 first:pl-5 last:pr-5 {{ $alineado($tipo) }}">{{ array_key_exists($k, $datos['totales']) ? $formato($datos['totales'][$k], $loop->first ? 'texto' : $tipo) : '' }}</td>
                                @endforeach
                            </tr>
                        </tfoot>
                    @endif
                </table>
            </div>
            <p class="border-t border-slate-200 px-5 py-3 text-xs text-slate-500">{{ number_format($datos['filas']->count()) }} {{ $datos['filas']->count() === 1 ? 'fila' : 'filas' }} · Generado el {{ now()->format('d/m/Y H:i') }}</p>
        @endif
    </section>
@endsection
