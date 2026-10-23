{{-- Portal de suscripciones (PortalController): $empresas [{empresa, actual, erp, web, pagos}], $periodicidades --}}
@extends('layouts.app')

@section('title', 'Mis suscripciones')

@php
    $estados = [
        'prueba' => ['info', 'En prueba'], 'activa' => ['success', 'Activa'], 'vencida' => ['warning', 'Vencida (en gracia)'],
        'suspendida' => ['danger', 'Suspendida'], 'cancelada' => ['neutral', 'Cancelada'],
    ];
    $tonosPago = ['pagado' => 'success', 'pendiente' => 'warning', 'anulado' => 'neutral'];
@endphp

@section('content')
    <x-page-header title="Mis suscripciones" :migas="['Suscripciones' => null]"
        subtitle="Los planes de todas las empresas que administras: ERP y Página web, quién los paga y hasta cuándo están pagados." />

    <x-flash />

    <div class="mt-6 space-y-5">
        @forelse ($empresas as $fila)
            <x-card :title="$fila->empresa->nombre_comercial ?: $fila->empresa->razon_social" icon="store" :subtitle="'RUC ' . $fila->empresa->ruc">
                <x-slot:aside>
                    @if ($fila->actual)
                        <a href="{{ route('config.suscripcion') }}" class="font-semibold text-brand-600 hover:underline">Ver detalle</a>
                    @else
                        <form method="POST" action="{{ route('empresa.cambiar') }}">
                            @csrf
                            <input type="hidden" name="empresa_id" value="{{ $fila->empresa->id }}">
                            <input type="hidden" name="ir" value="config.suscripcion">
                            <button type="submit" class="font-semibold text-brand-600 hover:underline">Cambiar a esta empresa y ver detalle</button>
                        </form>
                    @endif
                </x-slot:aside>

                <div class="grid gap-4 sm:grid-cols-2">
                    @foreach (['ERP' => $fila->erp, 'Página web' => $fila->web] as $producto => $s)
                        <div class="rounded-lg border border-slate-200 p-4">
                            <p class="text-xs font-medium uppercase tracking-wide text-slate-500">{{ $producto }}</p>
                            @if ($s)
                                @php [$tono, $etiqueta] = $estados[$s->estadoEfectivo()] ?? ['neutral', $s->estadoEfectivo()]; @endphp
                                <p class="mt-1 flex items-center gap-2 text-lg font-bold">{{ $s->plan->nombre }} <x-badge :tone="$tono" dot>{{ $etiqueta }}</x-badge></p>
                                <dl class="mt-2 space-y-1 text-sm">
                                    <div class="flex justify-between"><dt class="text-slate-500">{{ $s->estadoEfectivo() === 'prueba' ? 'Prueba hasta' : 'Pagado hasta' }}</dt><dd class="font-medium">{{ $s->vence_el->format('d/m/Y') }}</dd></div>
                                    <div class="flex justify-between"><dt class="text-slate-500">Periodo</dt><dd>{{ $periodicidades[$s->periodicidad][0] ?? $s->periodicidad }} · <x-money :value="$s->precio" /></dd></div>
                                    @if ($s->titular)<div class="flex justify-between gap-3"><dt class="text-slate-500">Paga</dt><dd class="truncate text-right">{{ $s->titular->nombre }}</dd></div>@endif
                                </dl>
                            @else
                                <p class="mt-1 text-sm text-slate-500">{{ $producto === 'ERP' ? 'Sin plan asignado.' : 'No contratada.' }}</p>
                            @endif
                        </div>
                    @endforeach
                </div>

                @if ($fila->pagos->isNotEmpty())
                    <table class="mt-4 w-full text-sm">
                        <thead class="text-left text-xs text-slate-500"><tr><th class="pb-1">Periodo</th><th class="pb-1">Plan</th><th class="pb-1 text-right">Monto</th><th class="pb-1">Estado</th><th class="pb-1">Comprobante</th></tr></thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach ($fila->pagos as $p)
                                <tr>
                                    <td class="py-1.5 whitespace-nowrap">{{ $p->periodo_desde->format('d/m/Y') }} – {{ $p->periodo_hasta->format('d/m/Y') }}</td>
                                    <td class="py-1.5">{{ $p->suscripcion?->plan?->producto?->nombre }} {{ $p->suscripcion?->plan?->nombre }}</td>
                                    <td class="py-1.5 text-right"><x-money :value="$p->monto" /></td>
                                    <td class="py-1.5"><x-badge :tone="$tonosPago[$p->estado] ?? 'neutral'" shape="box">{{ ucfirst($p->estado) }}</x-badge></td>
                                    <td class="py-1.5">{{ $p->comprobante?->numeroCompleto() ?? '—' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            </x-card>
        @empty
            <x-empty-state icon="sparkles" title="No administras ninguna empresa" description="Aquí verás los planes de las empresas en las que tienes el rol de administrador." />
        @endforelse
    </div>
@endsection
