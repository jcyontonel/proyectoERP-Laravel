{{-- MotivoAjusteController@index: $motivos, $usos [motivo_id => total], $tipos --}}
@extends('layouts.app')

@section('title', 'Motivos de ajuste')

@section('content')
    <x-page-header title="Motivos de ajuste" :migas="['Configuración' => null, 'Motivos de ajuste' => null]"
        subtitle="Razones de los ajustes de inventario. Los que requieren aprobación solo los aplica un usuario con permiso «aprobar ajustes».">
        <x-slot:acciones>
            <a href="{{ route('motivos.create') }}" class="btn btn-primary"><x-icon name="plus" /> Nuevo motivo</a>
        </x-slot:acciones>
    </x-page-header>

    <x-flash />

    <section class="mt-6 overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
        @if ($motivos->isEmpty())
            <x-empty-state icon="list-checks" title="Sin motivos" description="Crea los motivos que usarás en tus ajustes." />
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="border-b border-slate-200 bg-slate-50/70 text-left text-xs font-medium text-slate-500">
                        <tr>
                            <th class="py-3 pl-5 pr-3">Motivo</th>
                            <th class="px-3 py-3">Se usa en</th>
                            <th class="px-3 py-3">Aprobación</th>
                            <th class="px-3 py-3 text-right">Ajustes</th>
                            <th class="px-3 py-3">Estado</th>
                            <th class="py-3 pl-3 pr-5"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($motivos as $m)
                            <tr>
                                <td class="py-3 pl-5 pr-3"><p class="font-semibold">{{ $m->nombre }}</p><p class="text-xs text-slate-500">{{ $m->codigo }}</p></td>
                                <td class="px-3 py-3">{{ $tipos[$m->tipo_movimiento] ?? $m->tipo_movimiento }}</td>
                                <td class="px-3 py-3">@if ($m->requiere_aprobacion)<x-badge tone="warning" shape="box">Requiere</x-badge>@else<span class="text-xs text-slate-400">No</span>@endif</td>
                                <td class="px-3 py-3 text-right">{{ $usos[$m->id] ?? 0 }}</td>
                                <td class="px-3 py-3"><x-badge :tone="$m->activo ? 'success' : 'neutral'" :dot="true">{{ $m->activo ? 'Activo' : 'Inactivo' }}</x-badge></td>
                                <td class="py-3 pl-3 pr-5">
                                    <div class="flex justify-end gap-0.5">
                                        <a href="{{ route('motivos.edit', $m) }}" title="Editar" aria-label="Editar {{ $m->nombre }}" class="grid size-8 place-items-center rounded-md text-brand-600 hover:bg-brand-50"><x-icon name="pencil" /></a>
                                        @unless ($usos[$m->id] ?? 0)
                                            <button type="button" data-eliminar="{{ route('motivos.destroy', $m) }}" data-nombre="{{ $m->nombre }}" title="Eliminar" aria-label="Eliminar {{ $m->nombre }}" class="grid size-8 place-items-center rounded-md text-red-500 hover:bg-red-50"><x-icon name="trash" /></button>
                                        @endunless
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>

    <x-dialogo-eliminar titulo="Eliminar motivo" mensaje="Solo se eliminan motivos que no se han usado." />
@endsection
