{{--
    Configuracion\SuscripcionController
      $resumen  SuscripcionService::resumen (plan, suscripcion, estado, dias, limites)
      $pagos    PagoSuscripcion[] de la empresa   $planes  planes activos con precios
      $periodicidades  $metodos
--}}
@extends('layouts.app')

@section('title', 'Mi suscripción')

@php
    $s = $resumen['suscripcion'];
    $estados = [
        'prueba' => ['info', 'En prueba'], 'activa' => ['success', 'Activa'], 'vencida' => ['warning', 'Vencida (en gracia)'],
        'suspendida' => ['danger', 'Suspendida'], 'cancelada' => ['neutral', 'Cancelada'], 'sin_plan' => ['neutral', 'Sin plan'],
    ];
    [$tono, $etiqueta] = $estados[$resumen['estado']] ?? ['neutral', $resumen['estado']];
    $tonosPago = ['pagado' => 'success', 'pendiente' => 'warning', 'anulado' => 'neutral'];
@endphp

@section('content')
    <x-page-header title="Mi suscripción" :migas="['Configuración' => null, 'Mi suscripción' => null]"
        subtitle="Tu plan, hasta cuándo está pagado y cuánto has usado de cada límite. Para cambiar de plan o registrar un pago, comunícate con soporte.">
        <x-slot:badge><x-badge :tone="$tono" dot>{{ $etiqueta }}</x-badge></x-slot:badge>
        <x-slot:acciones>
            <a href="{{ route('suscripciones.portal') }}" class="btn btn-secondary"><x-icon name="layers" class="text-brand-600" /> Todas mis suscripciones</a>
        </x-slot:acciones>
    </x-page-header>

    <x-flash />

    <div class="mt-6 grid gap-5 lg:grid-cols-[minmax(0,1fr)_22rem]">
        <div class="space-y-5">
            <x-card title="Consumo del plan" icon="bar-chart" :subtitle="$s ? 'Los comprobantes se cuentan por mes calendario: el contador se reinicia el día 1.' : 'Tu empresa aún no tiene un plan asignado: por ahora no hay límites.'">
                <div class="space-y-4">
                    @foreach ($resumen['limites'] as $recurso => $l)
                        @php
                            // Solo los comprobantes del mes se "agotan"; productos, usuarios y almacenes en su tope es normal
                            $alerta = $recurso === 'comprobantes';
                            $lleno = $alerta && $l['maximo'] && $l['usado'] >= $l['maximo'];
                            $l['fraccion'] = $alerta ? $l['fraccion'] : 0;
                        @endphp
                        <div>
                            <div class="flex items-baseline justify-between text-sm">
                                <span class="font-medium">{{ ucfirst($l['etiqueta']) }}</span>
                                <span @class(['font-semibold', 'text-red-600' => $lleno])>
                                    {{ number_format($l['usado']) }} <span class="font-normal text-slate-500">/ {{ $l['maximo'] !== null ? number_format($l['maximo']) : 'ilimitado' }}</span>
                                </span>
                            </div>
                            @if ($l['maximo'])
                                <div class="mt-1.5 h-2 overflow-hidden rounded-full bg-slate-100" role="progressbar" aria-valuenow="{{ $l['usado'] }}" aria-valuemax="{{ $l['maximo'] }}" aria-label="{{ $l['etiqueta'] }}">
                                    <div @class(['h-full rounded-full', 'bg-red-500' => $lleno, 'bg-amber-500' => ! $lleno && $l['fraccion'] >= config('suscripciones.aviso_uso'), 'bg-brand-500' => $l['fraccion'] < config('suscripciones.aviso_uso')])
                                        style="width: {{ max(min($l['usado'] / $l['maximo'], 1) * 100, 2) }}%"></div>
                                </div>
                            @endif
                        </div>
                    @endforeach
                </div>
            </x-card>

            <x-card title="Pagos" icon="receipt">
                @if ($pagos->isEmpty())
                    <p class="text-sm text-slate-500">Aún no hay pagos registrados.</p>
                @else
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead class="text-left text-xs text-slate-500">
                                <tr><th class="py-2 pr-3">Periodo</th><th class="px-3 py-2">Plan</th><th class="px-3 py-2 text-right">Monto</th><th class="px-3 py-2">Estado</th><th class="px-3 py-2">Pagado el</th><th class="py-2 pl-3">Comprobante</th></tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                @foreach ($pagos as $p)
                                    <tr>
                                        <td class="py-2 pr-3 whitespace-nowrap">{{ $p->periodo_desde->format('d/m/Y') }} – {{ $p->periodo_hasta->format('d/m/Y') }}</td>
                                        <td class="px-3 py-2">{{ $p->suscripcion?->plan?->nombre }} <span class="text-xs text-slate-500">{{ $periodicidades[$p->suscripcion?->periodicidad][0] ?? '' }}</span></td>
                                        <td class="px-3 py-2 text-right">
                                            <x-money :value="$p->monto" />
                                            @if ($p->descuento > 0)<p class="text-[11px] text-emerald-700">−<x-money :value="$p->descuento" /> de descuento</p>@endif
                                        </td>
                                        <td class="px-3 py-2"><x-badge :tone="$tonosPago[$p->estado] ?? 'neutral'" shape="box">{{ ucfirst($p->estado) }}</x-badge></td>
                                        <td class="px-3 py-2 whitespace-nowrap text-slate-600">{{ $p->fecha_pago?->format('d/m/Y') ?? '—' }} @if ($p->metodo)<span class="text-xs text-slate-500">· {{ $metodos[$p->metodo] ?? $p->metodo }}</span>@endif</td>
                                        <td class="py-2 pl-3 whitespace-nowrap">
                                            @if ($c = $p->comprobante)
                                                <a href="{{ route('config.suscripcion.comprobante', [$p, 'impreso']) }}" target="_blank" rel="noopener" class="font-medium text-brand-600 hover:underline">{{ $c->numeroCompleto() }}</a>
                                                <x-badge :tone="$c->tono()" shape="box">{{ $c->fueAceptado() ? 'SUNAT ✓' : $c->etiqueta() }}</x-badge>
                                                @if ($c->tieneArchivos())
                                                    <a href="{{ route('config.suscripcion.comprobante', [$p, 'xml']) }}" class="ml-1 text-xs text-slate-500 hover:underline">XML</a>
                                                    @if ($c->cdr_path)<a href="{{ route('config.suscripcion.comprobante', [$p, 'cdr']) }}" class="ml-1 text-xs text-slate-500 hover:underline">CDR</a>@endif
                                                @endif
                                            @else
                                                <span class="text-slate-400">—</span>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </x-card>
        </div>

        <aside class="space-y-5">
            <x-card title="Plan actual" icon="sparkles">
                @if ($s)
                    <p class="text-2xl font-bold">{{ $s->plan->nombre }}</p>
                    <p class="text-sm text-slate-500">{{ $periodicidades[$s->periodicidad][0] ?? $s->periodicidad }} · <x-money :value="$s->precio" /></p>
                    <dl class="mt-4 space-y-2 text-sm">
                        <div class="flex justify-between"><dt class="text-slate-600">Desde</dt><dd>{{ $s->inicio->format('d/m/Y') }}</dd></div>
                        <div class="flex justify-between"><dt class="text-slate-600">{{ $resumen['estado'] === 'prueba' ? 'Prueba hasta' : 'Pagado hasta' }}</dt><dd class="font-semibold">{{ $s->vence_el->format('d/m/Y') }}</dd></div>
                        @if ($s->oferta)<div class="flex justify-between"><dt class="text-slate-600">Oferta</dt><dd>{{ $s->oferta->nombre }}</dd></div>@endif
                    </dl>
                @else
                    <p class="text-sm text-slate-500">Sin plan asignado.</p>
                @endif
            </x-card>

            <x-card title="Página web" icon="globe" :subtitle="$web ? null : 'Tu página con los productos que publiques: catálogo o tienda con carrito.'">
                @if ($web)
                    <p class="text-2xl font-bold">{{ $web->plan->nombre }}</p>
                    <p class="text-sm text-slate-500">{{ $periodicidades[$web->periodicidad][0] ?? $web->periodicidad }} · <x-money :value="$web->precio" /></p>
                    <dl class="mt-4 space-y-2 text-sm">
                        <div class="flex justify-between"><dt class="text-slate-600">{{ $web->estadoEfectivo() === 'prueba' ? 'Prueba hasta' : 'Pagado hasta' }}</dt><dd class="font-semibold">{{ $web->vence_el->format('d/m/Y') }}</dd></div>
                    </dl>
                @else
                    <p class="text-sm text-slate-500">No contratada. Para activarla, comunícate con soporte.</p>
                @endif
                <ul class="mt-3 space-y-2">
                    @foreach ($planesWeb as $plan)
                        <li @class(['rounded-lg border p-3', 'border-brand-500 bg-brand-50/50' => $web?->plan_id === $plan->id, 'border-slate-200' => $web?->plan_id !== $plan->id])>
                            <p class="font-semibold">{{ $plan->nombre }} @if ($web?->plan_id === $plan->id)<x-badge tone="info">Tu plan</x-badge>@endif</p>
                            <p class="mt-0.5 text-xs text-slate-500">{{ $plan->descripcion }}</p>
                            <p class="mt-1.5 text-xs">
                                @foreach ($plan->precios as $pr)
                                    <span class="mr-2 whitespace-nowrap"><strong><x-money :value="$pr->precio" /></strong> {{ strtolower($periodicidades[$pr->periodicidad][0] ?? $pr->periodicidad) }}</span>
                                @endforeach
                            </p>
                        </li>
                    @endforeach
                </ul>
            </x-card>

            <x-card title="Planes disponibles" icon="layers">
                <ul class="space-y-3">
                    @foreach ($planes as $plan)
                        <li @class(['rounded-lg border p-3', 'border-brand-500 bg-brand-50/50' => $s?->plan_id === $plan->id, 'border-slate-200' => $s?->plan_id !== $plan->id])>
                            <p class="font-semibold">{{ $plan->nombre }} @if ($s?->plan_id === $plan->id)<x-badge tone="info">Tu plan</x-badge>@endif</p>
                            <p class="mt-0.5 text-xs text-slate-500">
                                {{ $plan->max_comprobantes_mes ? number_format($plan->max_comprobantes_mes) . ' comprobantes/mes' : 'Comprobantes ilimitados' }} ·
                                {{ $plan->max_productos ? number_format($plan->max_productos) . ' productos' : 'productos ilimitados' }} ·
                                {{ $plan->max_usuarios ?? '∞' }} usuarios ·
                                {{ $plan->max_almacenes ?? '∞' }} {{ $plan->max_almacenes === 1 ? 'almacén' : 'almacenes' }}
                            </p>
                            {{-- Módulos: lo que incluye (✓) y lo que no (tachado) --}}
                            <ul class="mt-2 grid gap-x-3 gap-y-0.5 text-[11px] sm:grid-cols-2">
                                @foreach (\App\Modulos\Plataforma\Models\Plan::todasDe($plan->codigoProducto()) as $clave)
                                    @php $nombreModulo = \App\Modulos\Plataforma\Support\Modulos::nombre($clave); @endphp
                                    <li @class(['flex items-center gap-1', 'text-slate-700' => $plan->incluye($clave), 'text-slate-400 line-through' => ! $plan->incluye($clave)])>
                                        <x-icon :name="$plan->incluye($clave) ? 'check' : 'x'" :class="$plan->incluye($clave) ? 'size-3 text-emerald-600' : 'size-3'" />{{ $nombreModulo }}
                                    </li>
                                @endforeach
                            </ul>
                            <p class="mt-1.5 text-xs">
                                @foreach ($plan->precios as $pr)
                                    <span class="mr-2 whitespace-nowrap"><strong><x-money :value="$pr->precio" /></strong> {{ strtolower($periodicidades[$pr->periodicidad][0] ?? $pr->periodicidad) }}</span>
                                @endforeach
                            </p>
                        </li>
                    @endforeach
                </ul>
            </x-card>
        </aside>
    </div>
@endsection
