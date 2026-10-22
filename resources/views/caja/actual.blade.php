{{-- TurnoCajaController@actual (turno abierto): $sesion, $resumen, $movimientos, $pagos, $conceptos, $metodos --}}
@extends('layouts.app')

@section('title', 'Mi caja')

@section('content')
    <x-page-header :title="$sesion->caja?->nombre ?? 'Mi caja'" :migas="['Caja' => null, 'Turno actual' => null]"
        :subtitle="'Turno abierto el ' . $sesion->fecha_apertura->translatedFormat('d M') . ' a las ' . $sesion->fecha_apertura->format('H:i') . ' · ' . $sesion->fecha_apertura->diffForHumans(short: true)">
        <x-slot:badge><x-badge tone="success" :dot="true">Abierta</x-badge></x-slot:badge>
        <x-slot:acciones>
            <button type="button" data-movimiento="ingreso" class="btn btn-secondary"><x-icon name="trending-up" class="text-emerald-600" /> Ingreso</button>
            <button type="button" data-movimiento="egreso" class="btn btn-secondary"><x-icon name="trending-down" class="text-red-500" /> Egreso</button>
            <a href="{{ route('caja.cierre') }}" class="btn btn-primary"><x-icon name="lock" /> Cerrar turno</a>
        </x-slot:acciones>
    </x-page-header>

    <x-flash />

    <section class="mt-6 grid grid-cols-2 gap-4 xl:grid-cols-4">
        <x-stat-card title="Efectivo en caja" icon="banknote" tone="emerald" :value="'S/ ' . number_format($resumen['efectivo'], 2)">
            <span>esperado ahora mismo</span>
        </x-stat-card>
        <x-stat-card title="Total cobrado" icon="wallet" :value="'S/ ' . number_format($resumen['total_cobros'], 2)">
            <span>todos los métodos</span>
        </x-stat-card>
        <x-stat-card title="Ventas" icon="receipt" tone="slate" :value="number_format($resumen['ventas'])" :href="route('ventas.index')">
            <span>en este turno</span>
        </x-stat-card>
        <x-stat-card title="Egresos" icon="trending-down" tone="red" :value="'S/ ' . number_format($resumen['egresos'] + $resumen['total_pagos'], 2)">
            <span>gastos y pagos a proveedores</span>
        </x-stat-card>
    </section>

    <div class="mt-5 grid gap-5 lg:grid-cols-[minmax(0,1fr)_22rem]">
        @include('caja.partials.movimientos')
        <aside class="space-y-5 lg:sticky lg:top-24 lg:self-start">
            @include('caja.partials.resumen')
            @can('emitir-ventas')
                <a href="{{ route('ventas.create') }}" class="btn btn-secondary w-full"><x-icon name="plus" class="text-brand-600" /> Nueva venta</a>
            @endcan
        </aside>
    </div>

    @include('caja.partials.dialogo-movimiento')
@endsection
