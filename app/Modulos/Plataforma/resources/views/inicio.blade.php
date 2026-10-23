{{-- InicioController: $r = resumen del ERP (empresas por estado, mrr, cobrado_mes, pendiente, por_plan, vencen_pronto) --}}
@extends('plataforma::layouts.app')

@section('title', 'Panel')

@section('content')
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div>
            <h1 class="text-2xl font-bold tracking-tight sm:text-3xl">Panel</h1>
            <p class="mt-1.5 text-sm text-slate-500">Estado de las empresas clientes, ingresos y vencimientos de suscripciones.</p>
        </div>
        <a href="{{ route('plataforma.empresas.create') }}" class="btn btn-primary"><x-plataforma::icon name="plus" /> Nueva empresa</a>
    </div>

    <x-plataforma::flash />

    <div class="mt-6 grid grid-cols-2 gap-4 xl:grid-cols-4">
        <x-plataforma::stat-card title="Ingreso recurrente mensual" icon="trending-up" :value="'S/ ' . number_format($r['mrr'], 2)">De las suscripciones activas y en gracia</x-plataforma::stat-card>
        <x-plataforma::stat-card title="Cobrado este mes" icon="wallet" tone="emerald" :value="'S/ ' . number_format($r['cobrado_mes'], 2)" :href="route('plataforma.pagos.index', ['estado' => 'pagado', 'desde' => now()->startOfMonth()->toDateString()])">Pagos registrados</x-plataforma::stat-card>
        <x-plataforma::stat-card title="Por cobrar" icon="calendar-clock" tone="amber" :value="'S/ ' . number_format($r['pendiente']['total'], 2)" :href="route('plataforma.pagos.index', ['estado' => 'pendiente'])">{{ $r['pendiente']['cantidad'] }} cobros pendientes</x-plataforma::stat-card>
        <x-plataforma::stat-card title="Empresas" icon="building" tone="slate" :value="$r['empresas']['total']" :href="route('plataforma.empresas.index')">{{ $r['empresas']['sin_plan'] }} sin plan</x-plataforma::stat-card>
    </div>

    <div class="mt-6 grid gap-5 lg:grid-cols-[minmax(0,1fr)_22rem]">
        <x-plataforma::card title="Vencen pronto o están en gracia" icon="calendar-clock" subtitle="Los próximos 7 días. El cobro de renovación se genera solo.">
            @if (empty($r['vencen_pronto']))
                <p class="text-sm text-slate-500">Nada vence en los próximos días.</p>
            @else
                <ul class="divide-y divide-slate-100">
                    @foreach ($r['vencen_pronto'] as $v)
                        <li class="flex flex-wrap items-center justify-between gap-2 py-2.5 text-sm">
                            <a href="{{ route('plataforma.empresas.show', $v['empresa']['id']) }}" class="min-w-0 font-medium hover:text-brand-600">
                                {{ $v['empresa']['nombre_comercial'] ?: $v['empresa']['razon_social'] }}
                                <span class="block text-xs font-normal text-slate-500">{{ $v['plan'] }} · RUC {{ $v['empresa']['ruc'] }}</span>
                            </a>
                            <span class="flex items-center gap-2 text-xs">
                                {{ \Illuminate\Support\Carbon::parse($v['vence_el'])->format('d/m/Y') }}
                                <x-plataforma::estado :valor="$v['estado']" />
                            </span>
                        </li>
                    @endforeach
                </ul>
            @endif
        </x-plataforma::card>

        <div class="space-y-5">
            <x-plataforma::card title="Empresas por estado" icon="pie-chart">
                <dl class="space-y-2 text-sm">
                    @foreach (['activa', 'prueba', 'vencida', 'suspendida', 'sin_plan'] as $estado)
                        <div class="flex items-center justify-between">
                            <dt><x-plataforma::estado :valor="$estado" /></dt>
                            <dd><a href="{{ route('plataforma.empresas.index', ['estado' => $estado]) }}" class="font-semibold hover:text-brand-600">{{ $r['empresas'][$estado] }}</a></dd>
                        </div>
                    @endforeach
                </dl>
            </x-plataforma::card>
            <x-plataforma::card title="Por plan" icon="layers">
                <dl class="space-y-2 text-sm">
                    @forelse ($r['por_plan'] as $plan => $n)
                        <div class="flex justify-between"><dt class="text-slate-600">{{ $plan }}</dt><dd class="font-semibold">{{ $n }}</dd></div>
                    @empty
                        <p class="text-slate-500">Aún no hay suscripciones.</p>
                    @endforelse
                </dl>
            </x-plataforma::card>
        </div>
    </div>
@endsection
