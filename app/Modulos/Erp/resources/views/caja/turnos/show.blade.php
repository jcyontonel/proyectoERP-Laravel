{{-- TurnoCajaController@show: $sesion, $resumen, $movimientos, $pagos --}}
@extends('layouts.app')

@section('title', 'Turno de caja')

@section('content')
    <x-page-header :title="'Turno · ' . $sesion->caja?->nombre" :migas="['Caja' => route('caja.index'), 'Historial' => route('caja.turnos.index'), 'Turno' => null]"
        :subtitle="$sesion->usuario?->nombre_completo . ' · ' . $sesion->fecha_apertura->format('d/m/Y H:i') . ($sesion->fecha_cierre ? ' – ' . $sesion->fecha_cierre->format('d/m/Y H:i') : '')">
        <x-slot:badge><x-badge :tone="$sesion->estaAbierta() ? 'success' : 'neutral'" :dot="true">{{ $sesion->estaAbierta() ? 'Abierta' : 'Cerrada' }}</x-badge></x-slot:badge>
        <x-slot:acciones>
            <a href="{{ route('caja.turnos.imprimir', $sesion) }}" target="_blank" rel="noopener" class="btn btn-secondary"><x-icon name="printer" class="text-brand-600" /> Imprimir arqueo</a>
            @if ($sesion->estaAbierta() && $sesion->user_id === auth()->id())
                <a href="{{ route('caja.index') }}" class="btn btn-primary"><x-icon name="wallet" /> Ir a mi caja</a>
            @endif
        </x-slot:acciones>
    </x-page-header>

    <x-flash />

    @if ($sesion->observacion)
        <x-alert tone="info" class="mt-4"><span class="whitespace-pre-line">{{ $sesion->observacion }}</span></x-alert>
    @endif

    <div class="mt-6 grid gap-5 lg:grid-cols-[minmax(0,1fr)_22rem]">
        <div class="space-y-5">
            @include('caja.partials.movimientos')

            @if ($sesion->arqueo)
                <x-card title="Arqueo por denominación" icon="banknote">
                    <ul class="grid grid-cols-2 gap-2 text-sm sm:grid-cols-3">
                        @foreach (collect($sesion->arqueo)->sortKeysDesc() as $valor => $cantidad)
                            <li class="flex justify-between rounded-md bg-slate-50 px-3 py-2">
                                <span>S/ {{ $valor }} × {{ $cantidad }}</span>
                                <span class="font-semibold"><x-money :value="$valor * $cantidad" /></span>
                            </li>
                        @endforeach
                    </ul>
                </x-card>
            @endif
        </div>
        <aside class="space-y-5">
            @include('caja.partials.resumen')
            @if ($sesion->cerradoPor && $sesion->cerrado_por !== $sesion->user_id)
                <p class="text-xs text-slate-500">Cerrado por {{ $sesion->cerradoPor->nombre_completo }}.</p>
            @endif
        </aside>
    </div>
@endsection
