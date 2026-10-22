{{--
    Panel de inicio (InicioController → DashboardService::para). Variables:
      $widgets   [ventas, caja, inventario, finanzas, utilidad, compras, sunat] => bool (según permisos)
      $alcance   'empresa' | 'propio' (sin "ver-reportes" solo se ven las ventas propias)
      $fecha     Carbon · $stock (siempre)
      Según widget: $kpiVentas, $grafico, $ultimasVentas, $metodosPago, $topProductos, $caja,
                    $inventario, $porVencer, $cuentas, $utilidad, $compras, $sunat
--}}
@extends('layouts.app')

@section('title', 'Inicio')

@section('content')
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold tracking-tight sm:text-3xl">Hola, {{ str(auth()->user()->name)->before(' ') }}</h1>
            <p class="mt-1.5 text-sm text-slate-500">
                {{ $fecha->isToday() ? 'Este es el resumen de hoy' : 'Resumen del ' . $fecha->translatedFormat('l d \d\e F') }}
                en {{ $empresaActual?->nombre_comercial }}
                @if ($widgets['ventas'] && $alcance === 'propio') · <span class="font-medium text-ink">mostrando tus ventas</span> @endif
            </p>
        </div>

        {{-- Selector de día: el input date cubre el botón y envía el formulario al cambiar --}}
        <form method="GET" action="{{ route('inicio') }}">
            <label class="btn btn-secondary relative cursor-pointer font-medium">
                <x-icon name="calendar" class="text-slate-500" />
                {{ $fecha->isToday() ? 'Hoy, ' : '' }}{{ $fecha->translatedFormat('d M Y') }}
                <input type="date" name="fecha" value="{{ $fecha->format('Y-m-d') }}" max="{{ now()->format('Y-m-d') }}"
                    aria-label="Cambiar fecha" class="absolute inset-0 cursor-pointer opacity-0"
                    onclick="this.showPicker?.()" onchange="this.form.submit()">
            </label>
        </form>
    </div>

    <x-flash />

    @include('inicio.partials.kpis')
    @include('inicio.partials.acciones')

    <div class="mt-5 grid gap-5 lg:grid-cols-[minmax(0,1fr)_21rem] xl:grid-cols-[minmax(0,1fr)_24rem]">
        <div class="min-w-0 space-y-5">
            @if ($widgets['ventas'])
                @include('inicio.partials.grafico-semanal')
                @include('inicio.partials.ultimas-ventas')
            @endif
            @if ($widgets['utilidad'])
                @include('inicio.partials.utilidad')
            @endif
            <div class="grid gap-5 xl:grid-cols-2">
                @if ($widgets['ventas'])
                    @include('inicio.partials.top-productos')
                @endif
                @if ($widgets['compras'])
                    @include('inicio.partials.compras-recientes')
                @endif
            </div>
        </div>

        <div class="min-w-0 space-y-5">
            @if ($widgets['caja'])
                @include('inicio.partials.caja')
            @endif
            @include('inicio.partials.alertas-stock')
            @if ($widgets['inventario'])
                @include('inicio.partials.por-vencer')
            @endif
            @if ($widgets['finanzas'])
                @include('inicio.partials.cuentas')
            @endif
            @if ($widgets['ventas'])
                @include('inicio.partials.metodos-pago')
            @endif
            @if ($widgets['sunat'])
                @include('inicio.partials.sunat')
            @endif
        </div>
    </div>
@endsection
