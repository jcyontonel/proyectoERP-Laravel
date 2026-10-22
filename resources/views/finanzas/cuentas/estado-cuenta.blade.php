{{--
    Estado de cuenta de un cliente o proveedor (CuentasController::estadoCuenta)
      $c           textos y rutas del modo
      $tercero     Cliente | Proveedor
      $documentos  documentos del período con pagos (y su usuario)
      $pendientes  documentos con saldo (todas las fechas), del que vence antes al último
      $resumen     total, vencido, por_vencer, documentos, tramos
      $desde, $hasta, $facturado, $pagado  (período)
--}}
@extends('layouts.app')

@php
    $ruta = fn (string $accion, $p = []) => route($c['prefijo'] . '.' . $accion, $p);
    $nombre = ($c['nombre'])($tercero);
    $esCobro = $c['modo'] === \App\Services\CuentasService::COBRAR;
    $metodos = config('ventas.metodos_pago') + config('compras.metodos_pago');

    // Libro del período: cargos (documentos) y abonos (pagos), del más antiguo al más reciente, con saldo acumulado
    $movs = collect();
    foreach ($documentos as $d) {
        $movs->push((object) ['fecha' => $d->fecha_emision, 'tipo' => 'cargo', 'doc' => $d, 'pago' => null, 'monto' => (float) $d->total, 'anulado' => $d->esAnulada()]);
        foreach ($d->pagos as $p) {
            $movs->push((object) ['fecha' => $p->fecha_pago, 'tipo' => 'abono', 'doc' => $d, 'pago' => $p, 'monto' => (float) $p->monto, 'anulado' => $p->estado !== 'registrado' || $d->esAnulada()]);
        }
    }
    $saldo = 0;
    $movs = $movs->sortBy(fn ($m) => $m->fecha->format('YmdHis') . ($m->tipo === 'cargo' ? '0' : '1'))->values()
        ->each(function ($m) use (&$saldo) {
            if (! $m->anulado) {
                $saldo += $m->tipo === 'cargo' ? $m->monto : -$m->monto;
            }
            $m->saldo = round($saldo, 2);
        });
@endphp

@section('title', 'Estado de cuenta · ' . $nombre)

@section('content')
    <x-page-header :title="$nombre" :subtitle="($c['documento'])($tercero) . ' · Estado de cuenta'"
        :migas="['Finanzas' => null, $c['titulo'] => $ruta('index'), $nombre => null]">
        <x-slot:acciones>
            @if (! $c['permiso_tercero'] || auth()->user()->can($c['permiso_tercero']))
                <a href="{{ route($c['ruta_tercero'], $tercero) }}" class="btn btn-secondary"><x-icon name="user" class="text-brand-600" /> Ficha del {{ Str::lower($c['tercero']) }}</a>
            @endif
            @if ($resumen['total'] > 0)
                <button type="button" class="btn btn-primary"
                    data-pagar="{{ $ruta('abonar', $tercero) }}" data-titulo="Abono de {{ $nombre }}"
                    data-detalle="Se aplica del documento más antiguo al más reciente" data-saldo="{{ number_format($resumen['total'], 2, '.', '') }}">
                    <x-icon name="coins" /> Registrar abono
                </button>
            @endif
        </x-slot:acciones>
    </x-page-header>

    <x-flash class="mt-4" />

    <div class="mt-6 grid grid-cols-2 gap-4 xl:grid-cols-4">
        <x-stat-card :title="$esCobro ? 'Deuda del cliente' : 'Deuda con el proveedor'" icon="coins" :value="'S/ ' . number_format($resumen['total'], 2)">
            {{ $resumen['documentos'] }} {{ $resumen['documentos'] === 1 ? 'documento pendiente' : 'documentos pendientes' }}
        </x-stat-card>
        <x-stat-card title="Vencido" icon="alert-triangle" :tone="$resumen['vencido'] > 0 ? 'red' : 'slate'" :value="'S/ ' . number_format($resumen['vencido'], 2)">
            Vence en 7 días: <x-money :value="$resumen['por_vencer']" />
        </x-stat-card>
        <x-stat-card :title="$esCobro ? 'Vendido en el período' : 'Comprado en el período'" icon="file-text" tone="slate" :value="'S/ ' . number_format($facturado, 2)">
            {{ $desde->translatedFormat('d M Y') }} – {{ $hasta->translatedFormat('d M Y') }}
        </x-stat-card>
        <x-stat-card :title="$esCobro ? 'Cobrado en el período' : 'Pagado en el período'" icon="check-circle" tone="emerald" :value="'S/ ' . number_format($pagado, 2)">
            De los documentos del período
        </x-stat-card>
    </div>

    {{-- Pendientes (todas las fechas) --}}
    <section class="mt-6 rounded-xl border border-slate-200 bg-white shadow-sm" aria-labelledby="titulo-pendientes">
        <header class="p-4 sm:p-5">
            <h2 id="titulo-pendientes" class="text-base font-semibold">Documentos pendientes</h2>
            <p class="mt-0.5 text-xs text-slate-500">Un abono se aplica en este orden: primero lo que vence antes.</p>
        </header>
        @if ($pendientes->isEmpty())
            <x-empty-state class="border-t border-slate-200" icon="check-circle" title="Sin deuda pendiente"
                :description="$esCobro ? 'Este cliente no tiene ventas al crédito por cobrar.' : 'No hay compras por pagar a este proveedor.'" />
        @else
            @include('finanzas.cuentas.partials.documentos', ['filas' => $pendientes, 'sinTercero' => true])
        @endif
    </section>

    {{-- Libro del período --}}
    <section class="mt-6 rounded-xl border border-slate-200 bg-white shadow-sm" aria-labelledby="titulo-movimientos">
        <header class="flex flex-wrap items-end justify-between gap-3 p-4 sm:p-5">
            <div>
                <h2 id="titulo-movimientos" class="text-base font-semibold">Movimientos del período</h2>
                <p class="mt-0.5 text-xs text-slate-500">Cargos (documentos) y abonos ({{ $esCobro ? 'cobros' : 'pagos' }}) con el saldo acumulado del período.</p>
            </div>
            <form method="GET" class="flex flex-wrap items-end gap-2">
                <div class="field flex items-center gap-2 px-3 focus-within:border-brand-500 focus-within:ring-2 focus-within:ring-brand-500/20">
                    <x-icon name="calendar" class="text-slate-400" />
                    <input type="date" name="desde" value="{{ $desde->format('Y-m-d') }}" aria-label="Desde" class="min-w-0 border-0 bg-transparent p-0 text-sm focus:outline-none">
                    <span class="text-slate-400" aria-hidden="true">–</span>
                    <input type="date" name="hasta" value="{{ $hasta->format('Y-m-d') }}" aria-label="Hasta" class="min-w-0 border-0 bg-transparent p-0 text-sm focus:outline-none">
                </div>
                <button type="submit" class="btn btn-secondary"><x-icon name="sliders" class="text-brand-600" /> Ver</button>
            </form>
        </header>

        @if ($movs->isEmpty())
            <x-empty-state class="border-t border-slate-200" icon="history" title="Sin movimientos en el período" description="Amplía el rango de fechas." />
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="border-y border-slate-200 bg-slate-50/70 text-left text-xs font-medium text-slate-500">
                        <tr>
                            <th class="py-3 pl-5 pr-3">Fecha</th>
                            <th class="px-3 py-3">Concepto</th>
                            <th class="px-3 py-3 text-right">Cargo</th>
                            <th class="px-3 py-3 text-right">Abono</th>
                            <th class="px-3 py-3 text-right">Saldo</th>
                            <th class="py-3 pl-3 pr-5"><span class="sr-only">Acciones</span></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($movs as $m)
                            <tr @class(['hover:bg-slate-50/60', 'text-slate-400' => $m->anulado])>
                                <td class="whitespace-nowrap py-3 pl-5 pr-3">{{ $m->fecha->translatedFormat('d M Y') }}</td>
                                <td class="px-3 py-3">
                                    @if ($m->tipo === 'cargo')
                                        <a href="{{ route($c['ruta_doc'], $m->doc) }}" class="font-medium text-brand-600 hover:text-brand-700">{{ $c['documento_label'] }} {{ $m->doc->numero_completo }}</a>
                                        @if ($m->anulado) <x-badge tone="neutral" shape="box">Anulada</x-badge> @endif
                                        @if ($m->doc->fecha_vencimiento)<p class="text-xs text-slate-500">Vence {{ $m->doc->fecha_vencimiento->translatedFormat('d M Y') }}</p>@endif
                                    @else
                                        <p @class(['font-medium', 'line-through' => $m->anulado])>{{ ucfirst($m->pago->tipo) }} · {{ $metodos[$m->pago->metodo_pago] ?? $m->pago->metodo_pago }}</p>
                                        <p class="text-xs text-slate-500">
                                            {{ $m->doc->numero_completo }}
                                            @if ($m->pago->referencia) · Op. {{ $m->pago->referencia }} @endif
                                            @if ($m->pago->usuario) · {{ $m->pago->usuario->name }} @endif
                                            @if ($m->pago->estado !== 'registrado') · anulado @endif
                                        </p>
                                    @endif
                                </td>
                                <td class="px-3 py-3 text-right">@if ($m->tipo === 'cargo')<span @class(['line-through' => $m->anulado])><x-money :value="$m->monto" /></span>@endif</td>
                                <td class="px-3 py-3 text-right text-emerald-700">@if ($m->tipo === 'abono')<span @class(['line-through text-slate-400' => $m->anulado])>− <x-money :value="$m->monto" /></span>@endif</td>
                                <td class="px-3 py-3 text-right font-semibold"><x-money :value="$m->saldo" /></td>
                                <td class="py-3 pl-3 pr-5 text-right">
                                    @if ($m->tipo === 'abono' && ! $m->anulado)
                                        <button type="button" data-anular-pago="{{ route('finanzas.movimientos.anular', $m->pago) }}"
                                            data-numero="{{ ucfirst($m->pago->tipo) }} de S/ {{ number_format($m->monto, 2) }} ({{ $m->doc->numero_completo }})"
                                            title="Anular" class="grid size-8 place-items-center rounded-md text-red-500 hover:bg-red-50"><x-icon name="x-circle" /></button>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>

    @include('finanzas.partials.dialogo-pago', ['metodos' => $c['metodos'], 'metodoDefecto' => $esCobro ? 'efectivo' : 'transferencia'])
    @include('finanzas.partials.dialogo-anular-pago')
@endsection
