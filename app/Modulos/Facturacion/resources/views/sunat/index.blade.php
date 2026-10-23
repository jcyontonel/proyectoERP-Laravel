{{-- ComprobanteSunatController@index: $comprobantes (paginador), $conteo [estado => cantidad], $bajas (solo anulaciones), $activo --}}
@extends('layouts.app')

@php
    use App\Modulos\Facturacion\Models\Documento;
    use App\Modulos\Erp\Models\Nota;
    $titulo = $bajas ? 'Resúmenes y bajas' : 'Envíos a SUNAT';
    $ruta = $bajas ? 'sunat.resumenes' : 'sunat.index';
    $tipos = ['01' => 'Factura', '03' => 'Boleta', '07' => 'Nota de crédito', '08' => 'Nota de débito'];
    $hayFiltros = request()->hasAny(['q', 'estado', 'tipo', 'desde', 'hasta']);
@endphp

@section('title', $titulo)

@section('content')
    <x-page-header :title="$titulo" :migas="['SUNAT' => null, $titulo => null]"
        :subtitle="$bajas
            ? 'Anulaciones enviadas a SUNAT: comunicación de baja (facturas) y resumen diario (boletas). El resultado llega solo.'
            : 'Cada factura, boleta y nota se firma y se envía a SUNAT en segundo plano. Si no hay internet queda pendiente y se reenvía automáticamente.'" />

    <x-flash />

    @unless ($activo)
        <x-alert tone="warning" class="mt-4">La facturación electrónica está apagada (<code>FACTURACION_ACTIVA=false</code> en el <code>.env</code>).</x-alert>
    @endunless

    @unless ($bajas)
        <div class="mt-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <x-stat-card title="Aceptados" icon="check-circle" tone="emerald" :value="(int) (($conteo['aceptado'] ?? 0) + ($conteo['observado'] ?? 0))" :href="route($ruta, ['estado' => 'aceptado'])">{{ (int) ($conteo['observado'] ?? 0) }} con observaciones</x-stat-card>
            <x-stat-card title="Pendientes de envío" icon="refresh" tone="amber" :value="(int) ($conteo['pendiente'] ?? 0)" :href="route($ruta, ['estado' => 'pendiente'])">se reenvían solos</x-stat-card>
            <x-stat-card title="Rechazados" icon="x-circle" tone="red" :value="(int) ($conteo['rechazado'] ?? 0)" :href="route($ruta, ['estado' => 'rechazado'])">corregir y reenviar</x-stat-card>
            <x-stat-card title="Con error" icon="alert-triangle" tone="red" :value="(int) ($conteo['error'] ?? 0)" :href="route($ruta, ['estado' => 'error'])">revisar configuración</x-stat-card>
        </div>
    @endunless

    <form method="GET" class="mt-6 grid items-end gap-4 rounded-xl border border-slate-200 bg-white p-4 shadow-sm sm:grid-cols-2 sm:p-5 lg:grid-cols-[1.5fr_1fr_1fr_1fr_1fr_auto]">
        <div class="sm:col-span-2 lg:col-span-1">
            <div class="flex items-center justify-between">
                <label for="q" class="field-label !mb-0">Comprobante</label>
                @if ($hayFiltros)<a href="{{ route($ruta) }}" class="text-xs font-semibold text-brand-600">Limpiar</a>@endif
            </div>
            <input id="q" name="q" type="search" value="{{ request('q') }}" placeholder="F001-125" class="field mt-1.5">
        </div>
        <x-select name="tipo" label="Tipo">
            <option value="">Todos</option>
            @foreach ($tipos as $v => $t)<option value="{{ $v }}" @selected(request('tipo') === $v)>{{ $t }}</option>@endforeach
        </x-select>
        <x-select name="estado" label="Estado">
            <option value="">Todos</option>
            @foreach (Documento::ESTADOS as $v => [$t])<option value="{{ $v }}" @selected(request('estado') === $v)>{{ $t }}</option>@endforeach
        </x-select>
        <x-input name="desde" label="Desde" type="date" :value="request('desde')" />
        <x-input name="hasta" label="Hasta" type="date" :value="request('hasta')" />
        <button type="submit" class="btn btn-secondary"><x-icon name="sliders" class="text-brand-600" /> Filtrar</button>
    </form>

    <section class="mt-6 overflow-x-auto rounded-xl border border-slate-200 bg-white shadow-sm">
        @if ($comprobantes->isEmpty())
            <x-empty-state icon="upload-cloud" :title="$bajas ? 'Sin anulaciones' : 'Sin envíos'" :description="$bajas ? 'Al anular una factura o boleta aceptada, su baja aparecerá aquí.' : 'Las facturas, boletas y notas que registres aparecerán aquí con su estado en SUNAT.'" />
        @else
            <table class="w-full text-sm">
                <thead class="border-b border-slate-200 bg-slate-50/70 text-left text-xs font-medium text-slate-500">
                    <tr><th class="py-3 pl-5 pr-3">Comprobante</th><th class="px-3 py-3">Estado</th><th class="px-3 py-3">Respuesta</th>@if ($bajas)<th class="px-3 py-3">Baja</th>@endif<th class="px-3 py-3">Envíos</th><th class="py-3 pl-3 pr-5 text-right">Acciones</th></tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach ($comprobantes as $c)
                        @php
                            $doc = $c->comprobante;
                            $url = match (true) {
                                $doc instanceof Nota => route('notas.show', $doc),
                                $doc instanceof \App\Modulos\Erp\Models\Venta => route('ventas.show', $doc),
                                default => null,   // pago de suscripción: se gestiona en el panel admin
                            };
                        @endphp
                        <tr class="align-top hover:bg-slate-50/60">
                            <td class="py-3 pl-5 pr-3">
                                @if ($url)<a href="{{ $url }}" class="font-semibold text-brand-600 hover:text-brand-700">{{ $c->numeroCompleto() }}</a>@else<span class="font-semibold">{{ $c->numeroCompleto() }}</span>@endif
                                <p class="text-xs text-slate-500">{{ $tipos[$c->tipo] ?? $c->tipo }} · {{ ($c->fecha_emision ?? $c->created_at)->format('d/m/Y H:i') }}</p>
                                @if ($doc instanceof \App\Modulos\Plataforma\Models\PagoSuscripcion)
                                    <p class="text-xs text-slate-500">Suscripción de {{ $doc->empresa?->razon_social }}</p>
                                @endif
                            </td>
                            <td class="px-3 py-3"><x-badge :tone="$c->tono()" dot>{{ $c->etiqueta() }}</x-badge></td>
                            <td class="max-w-md px-3 py-3 text-xs text-slate-600">
                                @if ($c->sunat_codigo !== null)<span class="font-medium">[{{ $c->sunat_codigo }}]</span>@endif
                                {{ \Illuminate\Support\Str::limit($c->mensaje(), 180) }}
                            </td>
                            @if ($bajas)
                                <td class="px-3 py-3 text-xs">{{ $c->baja?->numeroCompleto() }} · {{ $c->baja_estado }}@if ($c->baja?->ticket)<br><span class="text-slate-400">ticket {{ $c->baja->ticket }}</span>@endif</td>
                            @endif
                            <td class="px-3 py-3 text-xs text-slate-500">{{ $c->intentos }}</td>
                            <td class="whitespace-nowrap py-3 pl-3 pr-5 text-right">
                                @if ($c->esReenviable())
                                    <form method="POST" action="{{ route('sunat.reenviar', $c) }}" class="inline">
                                        @csrf
                                        <button type="submit" class="text-xs font-semibold text-brand-600 hover:underline">Reenviar</button>
                                    </form>
                                @endif
                                @if ($c->tieneArchivos())
                                    <a href="{{ route('sunat.archivo', [$c, 'xml']) }}" class="ml-2 text-xs font-semibold text-slate-600 hover:underline">XML</a>
                                    @if ($c->cdr_path)
                                        <a href="{{ route('sunat.archivo', [$c, 'cdr']) }}" class="ml-2 text-xs font-semibold text-slate-600 hover:underline">CDR</a>
                                    @endif
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            @if ($comprobantes->hasPages())
                <div class="border-t border-slate-200 px-4 py-3 sm:px-5">{{ $comprobantes->links('vendor.pagination.ricopet') }}</div>
            @endif
        @endif
    </section>
@endsection
