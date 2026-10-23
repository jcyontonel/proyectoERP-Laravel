{{-- Página web → Zonas de envío (ZonaEnvioController@index): $zonas --}}
@extends('layouts.app')

@section('title', 'Zonas de envío')

@section('content')
    <x-page-header title="Zonas de envío" :migas="['Página web' => route('web.tienda.edit'), 'Zonas de envío' => null]"
        subtitle="Dónde repartes, cuánto cuesta el envío y en cuánto tiempo llega. El cliente elige su zona al hacer el pedido." />

    <x-flash />

    <div class="mt-6 grid gap-5 lg:grid-cols-[minmax(0,1fr)_20rem]">
        <section class="space-y-3">
            @forelse ($zonas as $z)
                <form method="POST" action="{{ route('web.zonas.update', $z) }}" @class(['grid items-end gap-3 rounded-xl border border-slate-200 bg-white p-4 shadow-sm sm:grid-cols-[minmax(0,1fr)_7rem_10rem_auto]', 'opacity-60' => ! $z->activo])>
                    @csrf @method('PUT')
                    <div>
                        <label class="field-label" for="nombre-{{ $z->id }}">Zona</label>
                        <input id="nombre-{{ $z->id }}" name="nombre" value="{{ $z->nombre }}" required maxlength="100" class="field">
                    </div>
                    <div>
                        <label class="field-label" for="costo-{{ $z->id }}">Costo (S/)</label>
                        <input id="costo-{{ $z->id }}" name="costo" type="number" step="0.01" min="0" value="{{ $z->costo }}" required class="field">
                    </div>
                    <div>
                        <label class="field-label" for="tiempo-{{ $z->id }}">Tiempo</label>
                        <input id="tiempo-{{ $z->id }}" name="tiempo_entrega" value="{{ $z->tiempo_entrega }}" maxlength="60" class="field" placeholder="24 h">
                    </div>
                    <div class="flex items-center gap-3">
                        <label class="flex items-center gap-1.5 text-xs"><input type="hidden" name="activo" value="0"><input type="checkbox" name="activo" value="1" @checked($z->activo) class="rounded border-slate-300"> Activa</label>
                        <button type="submit" class="btn btn-secondary">Guardar</button>
                        <button type="submit" form="eliminar-{{ $z->id }}" class="text-xs text-red-500 hover:underline">Eliminar</button>
                    </div>
                </form>
                <form id="eliminar-{{ $z->id }}" method="POST" action="{{ route('web.zonas.destroy', $z) }}">@csrf @method('DELETE')</form>
            @empty
                <x-empty-state icon="truck" title="Sin zonas de envío" description="Agrega las zonas donde repartes. Sin zonas, tus clientes solo pueden recoger en tienda." />
            @endforelse
        </section>

        <x-card title="Nueva zona" icon="plus-circle">
            <form method="POST" action="{{ route('web.zonas.store') }}" class="space-y-4">
                @csrf
                <x-input name="nombre" label="Zona *" required maxlength="100" placeholder="Miraflores, San Isidro" />
                <x-input name="costo" label="Costo (S/) *" type="number" step="0.01" min="0" required value="0" />
                <x-input name="tiempo_entrega" label="Tiempo de entrega" maxlength="60" placeholder="24 a 48 horas" />
                <button type="submit" class="btn btn-primary w-full"><x-icon name="plus" /> Agregar</button>
            </form>
        </x-card>
    </div>
@endsection
