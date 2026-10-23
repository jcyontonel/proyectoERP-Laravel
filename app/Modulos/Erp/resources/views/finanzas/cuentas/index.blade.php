{{--
    Cuentas por cobrar / por pagar (CuentasController@index)
      $c          textos y rutas del modo (CuentasCobrarController / CuentasPagarController::config())
      $vista      documentos | terceros
      $resumen    total, vencido, por_vencer, documentos, terceros, tramos[tramo => saldo]
      $tramos     [clave => etiqueta]
      $filas      LengthAwarePaginator de Venta|Compra (vista documentos) u objetos por tercero
      $filtrados  total y documentos según filtros
    Filtros (GET): q, estado, tramo, vista
--}}
@extends('layouts.app')

@section('title', $c['titulo'])

@php
    $ruta = fn (string $accion, $p = []) => route($c['prefijo'] . '.' . $accion, $p);
    $hayFiltros = request()->hasAny(['q', 'estado', 'tramo']);
    $coloresTramo = ['al_dia' => 'bg-emerald-500', '1_30' => 'bg-amber-400', '31_60' => 'bg-orange-500', '61_90' => 'bg-red-500', 'mas_90' => 'bg-red-700'];
    $esCobro = $c['modo'] === \App\Modulos\Erp\Services\CuentasService::COBRAR;
@endphp

@section('content')
    <x-page-header :title="$c['titulo']" :subtitle="$c['subtitulo']" :migas="['Finanzas' => null, $c['titulo'] => null]">
        <x-slot:acciones>
            <a href="{{ route('finanzas.movimientos.index', ['tipo' => $esCobro ? 'cobro' : 'pago']) }}" class="btn btn-secondary">
                <x-icon name="wallet" class="text-brand-600" /> Historial de {{ $esCobro ? 'cobros' : 'pagos' }}
            </a>
        </x-slot:acciones>
    </x-page-header>

    <x-flash class="mt-4" />

    {{-- Indicadores --}}
    <div class="mt-6 grid grid-cols-2 gap-4 xl:grid-cols-4">
        <x-stat-card :title="$esCobro ? 'Total por cobrar' : 'Total por pagar'" icon="coins" :value="'S/ ' . number_format($resumen['total'], 2)">
            {{ $resumen['documentos'] }} {{ $resumen['documentos'] === 1 ? 'documento' : 'documentos' }} · {{ $resumen['terceros'] }} {{ Str::lower($resumen['terceros'] === 1 ? $c['tercero'] : $c['terceros']) }}
        </x-stat-card>
        <x-stat-card title="Vencido" icon="alert-triangle" tone="red" :value="'S/ ' . number_format($resumen['vencido'], 2)"
            :href="$ruta('index', ['estado' => 'vencidas'])">
            {{ $resumen['total'] > 0 ? number_format($resumen['vencido'] / $resumen['total'] * 100, 1) : 0 }} % de la deuda
        </x-stat-card>
        <x-stat-card title="Vence en 7 días" icon="calendar-clock" tone="amber" :value="'S/ ' . number_format($resumen['por_vencer'], 2)"
            :href="$ruta('index', ['estado' => 'por_vencer'])">
            {{ $esCobro ? 'Recuérdale al cliente' : 'Programa el pago' }}
        </x-stat-card>
        <x-stat-card title="Al día" icon="check-circle" tone="emerald" :value="'S/ ' . number_format($resumen['total'] - $resumen['vencido'], 2)"
            :href="$ruta('index', ['estado' => 'al_dia'])">
            Sin atraso
        </x-stat-card>
    </div>

    {{-- Antigüedad de la deuda --}}
    <x-card title="Antigüedad de la deuda" icon="bar-chart" subtitle="Saldo según los días de atraso desde el vencimiento. Haz clic en un tramo para filtrar." class="mt-6">
        @if ($resumen['total'] > 0)
            <div class="flex h-3 overflow-hidden rounded-full bg-slate-100" role="img" aria-label="Distribución de la deuda por antigüedad">
                @foreach ($tramos as $clave => $etiqueta)
                    @if ($resumen['tramos'][$clave] > 0)
                        <span class="{{ $coloresTramo[$clave] }}" style="width: {{ $resumen['tramos'][$clave] / $resumen['total'] * 100 }}%" title="{{ $etiqueta }}: S/ {{ number_format($resumen['tramos'][$clave], 2) }}"></span>
                    @endif
                @endforeach
            </div>
        @endif
        <div class="mt-4 grid grid-cols-2 gap-2 sm:grid-cols-3 lg:grid-cols-5">
            @foreach ($tramos as $clave => $etiqueta)
                <a href="{{ $ruta('index', array_filter(['tramo' => request('tramo') === $clave ? null : $clave, 'vista' => request('vista')])) }}"
                    @class(['rounded-lg border p-3 transition hover:border-brand-200 hover:bg-slate-50',
                        'border-brand-500 bg-brand-50' => request('tramo') === $clave, 'border-slate-200' => request('tramo') !== $clave])>
                    <p class="flex items-center gap-1.5 text-xs text-slate-500"><span class="size-2 rounded-full {{ $coloresTramo[$clave] }}"></span> {{ $etiqueta }}</p>
                    <p class="mt-1 font-semibold"><x-money :value="$resumen['tramos'][$clave]" /></p>
                </a>
            @endforeach
        </div>
    </x-card>

    {{-- Filtros --}}
    <form method="GET" action="{{ $ruta('index') }}" class="mt-6 rounded-xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5">
        <input type="hidden" name="vista" value="{{ $vista }}">
        <div class="grid items-end gap-4 sm:grid-cols-2 lg:grid-cols-[2fr_1fr_1fr_auto]">
            <div class="sm:col-span-2 lg:col-span-1">
                <div class="flex items-center justify-between">
                    <label for="q" class="field-label !mb-0">Buscar</label>
                    @if ($hayFiltros)
                        <a href="{{ $ruta('index', ['vista' => $vista]) }}" class="text-xs font-semibold text-brand-600 hover:text-brand-700">Limpiar filtros</a>
                    @endif
                </div>
                <div class="relative mt-1.5">
                    <x-icon name="search" class="pointer-events-none absolute left-3 top-3 text-slate-400" />
                    <input id="q" name="q" type="search" value="{{ request('q') }}" autocomplete="off"
                        placeholder="Comprobante, {{ Str::lower($c['tercero']) }}, DNI o RUC..." class="field pl-9">
                </div>
            </div>
            <x-select name="estado" label="Vencimiento">
                <option value="">Todos</option>
                @foreach (['vencidas' => 'Vencidas', 'por_vencer' => 'Vencen en 7 días', 'al_dia' => 'Al día'] as $valor => $etiqueta)
                    <option value="{{ $valor }}" @selected(request('estado') === $valor)>{{ $etiqueta }}</option>
                @endforeach
            </x-select>
            <x-select name="tramo" label="Antigüedad">
                <option value="">Todas</option>
                @foreach ($tramos as $valor => $etiqueta)
                    <option value="{{ $valor }}" @selected(request('tramo') === $valor)>{{ $etiqueta }}</option>
                @endforeach
            </x-select>
            <button type="submit" class="btn btn-secondary w-full"><x-icon name="sliders" class="text-brand-600" /> Filtrar</button>
        </div>
    </form>

    {{-- Listado --}}
    <section class="mt-6 rounded-xl border border-slate-200 bg-white shadow-sm" aria-labelledby="titulo-listado">
        <header class="flex flex-wrap items-center justify-between gap-3 p-4 sm:p-5">
            <div>
                <h2 id="titulo-listado" class="text-base font-semibold">{{ $vista === 'terceros' ? 'Deuda por ' . Str::lower($c['tercero']) : 'Documentos pendientes' }}</h2>
                <p class="mt-0.5 text-xs text-slate-500">
                    {{ $filtrados['documentos'] }} {{ $filtrados['documentos'] === 1 ? 'documento' : 'documentos' }} · saldo <strong class="text-ink"><x-money :value="$filtrados['total']" /></strong>
                    @if ($vista === 'documentos') · Primero lo que vence antes @endif
                </p>
            </div>
            <nav class="flex rounded-lg bg-slate-100 p-1 text-xs font-semibold" aria-label="Vista">
                @foreach (['documentos' => 'Por documento', 'terceros' => 'Por ' . Str::lower($c['tercero'])] as $valor => $etiqueta)
                    <a href="{{ $ruta('index', array_filter(request()->only(['q', 'estado', 'tramo'])) + ['vista' => $valor]) }}"
                        @if ($vista === $valor) aria-current="page" @endif
                        @class(['rounded-md px-3 py-1.5', 'bg-white text-ink shadow-sm' => $vista === $valor, 'text-slate-500 hover:text-ink' => $vista !== $valor])>{{ $etiqueta }}</a>
                @endforeach
            </nav>
        </header>

        @if ($filas->isEmpty())
            <x-empty-state class="border-t border-slate-200" icon="check-circle"
                :title="$hayFiltros ? 'Nada coincide con los filtros' : ($esCobro ? 'No hay cuentas por cobrar' : 'No hay cuentas por pagar')"
                :description="$hayFiltros ? 'Prueba con otros filtros.' : ($esCobro ? 'Las ventas al crédito con saldo aparecerán aquí.' : 'Las compras con saldo pendiente aparecerán aquí.')" />
        @elseif ($vista === 'terceros')
            @include('finanzas.cuentas.partials.por-tercero')
        @else
            @include('finanzas.cuentas.partials.documentos')
        @endif

        @if ($filas->hasPages())
            <div class="border-t border-slate-200 px-4 py-3 sm:px-5">{{ $filas->links('vendor.pagination.ricopet') }}</div>
        @endif
    </section>

    @include('finanzas.partials.dialogo-pago', ['metodos' => $c['metodos'], 'metodoDefecto' => $esCobro ? 'efectivo' : 'transferencia'])
@endsection
