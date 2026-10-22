{{-- CajaController@index: $cajas (almacen, sesionAbierta.usuario, sesiones_count) --}}
@extends('layouts.app')

@section('title', 'Cajas')

@section('content')
    <x-page-header title="Cajas registradoras" :migas="['Configuración' => null, 'Cajas' => null]" subtitle="Cada caja admite un turno abierto a la vez. Asígnala al almacén o sede donde está.">
        <x-slot:acciones>
            <a href="{{ route('almacenes.index') }}" class="btn btn-secondary"><x-icon name="warehouse" class="text-brand-600" /> Almacenes</a>
            <a href="{{ route('cajas.create') }}" class="btn btn-primary"><x-icon name="plus" /> Nueva caja</a>
        </x-slot:acciones>
    </x-page-header>

    <x-flash />

    <section class="mt-6 overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
        @if ($cajas->isEmpty())
            <x-empty-state icon="wallet" title="Sin cajas" description="Crea al menos una caja para abrir turnos y cobrar ventas." />
        @else
            <ul class="divide-y divide-slate-100">
                @foreach ($cajas as $c)
                    <li @class(['flex flex-wrap items-center gap-4 px-4 py-3.5 sm:px-5', 'opacity-60' => ! $c->activo])>
                        <span class="grid size-10 shrink-0 place-items-center rounded-lg bg-brand-50 text-brand-600"><x-icon name="wallet" /></span>
                        <div class="min-w-0 flex-1">
                            <p class="font-semibold">{{ $c->nombre }} <span class="text-xs font-normal text-slate-500">· {{ $c->codigo }}</span></p>
                            <p class="text-xs text-slate-500">{{ $c->almacen?->nombre ?? 'Sin almacén' }} · {{ $c->sesiones_count }} turnos registrados</p>
                        </div>
                        @if ($c->sesionAbierta)
                            <x-badge tone="success" :dot="true">Abierta · {{ $c->sesionAbierta->usuario?->nombre_completo }}</x-badge>
                        @elseif ($c->activo)
                            <x-badge tone="neutral" :dot="true">Cerrada</x-badge>
                        @else
                            <x-badge tone="neutral">Inactiva</x-badge>
                        @endif
                        <div class="flex gap-0.5">
                            <a href="{{ route('caja.turnos.index', ['caja_id' => $c->id]) }}" title="Turnos" aria-label="Turnos de {{ $c->nombre }}" class="grid size-8 place-items-center rounded-md text-brand-600 hover:bg-brand-50"><x-icon name="history" /></a>
                            <a href="{{ route('cajas.edit', $c) }}" title="Editar" aria-label="Editar {{ $c->nombre }}" class="grid size-8 place-items-center rounded-md text-brand-600 hover:bg-brand-50"><x-icon name="pencil" /></a>
                            <button type="button" data-eliminar="{{ route('cajas.destroy', $c) }}" data-nombre="{{ $c->nombre }}" title="Eliminar" aria-label="Eliminar {{ $c->nombre }}" class="grid size-8 place-items-center rounded-md text-red-500 hover:bg-red-50"><x-icon name="trash" /></button>
                        </div>
                    </li>
                @endforeach
            </ul>
        @endif
    </section>

    <x-dialogo-eliminar titulo="Eliminar caja" mensaje="Solo se eliminan cajas sin turnos registrados." />
@endsection
