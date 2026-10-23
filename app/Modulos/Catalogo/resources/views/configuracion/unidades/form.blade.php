{{-- UnidadController@create / edit: $unidad, $sunat (códigos frecuentes del catálogo 03), $bloqueado --}}
@extends('layouts.app')

@php $editando = $unidad->exists; @endphp

@section('title', $editando ? $unidad->descripcion : 'Nueva unidad')

@section('content')
    <x-page-header :title="$editando ? $unidad->descripcion : 'Nueva unidad de medida'"
        :migas="['Configuración' => null, 'Unidades de medida' => route('unidades.index'), ($editando ? $unidad->codigo : 'Nueva') => null]"
        subtitle="Las unidades son comunes a todas las empresas: una vez creada, cualquier empresa puede usarla." />

    <x-flash />

    @if ($bloqueado)
        <x-alert tone="info" class="mt-6 max-w-2xl">Otras empresas usan esta unidad: se muestra en solo lectura.</x-alert>
    @endif

    <form method="POST" action="{{ $editando ? route('unidades.update', $unidad) : route('unidades.store') }}" class="mt-6 max-w-2xl">
        @csrf
        @if ($editando) @method('PUT') @endif

        <x-card title="Unidad de medida" icon="ruler">
            <fieldset @disabled($bloqueado) class="grid gap-4 sm:grid-cols-[9rem_minmax(0,1fr)]">
                <x-input name="codigo" label="Código *" :value="$unidad->codigo" required maxlength="10" class="font-mono uppercase" placeholder="SCO15" />
                <x-input name="descripcion" label="Descripción *" :value="$unidad->descripcion" required maxlength="255" placeholder="Saco de 15 kg" />
                <div class="sm:col-span-2">
                    <x-input name="codigo_sunat" label="Código SUNAT (catálogo 03)" :value="$unidad->codigo_sunat" maxlength="5" list="codigos-sunat" class="font-mono uppercase"
                        hint="Elige de la lista o escribe otro código del catálogo 03. Para bienes por unidad usa NIU; para servicios, ZZ." />
                    <datalist id="codigos-sunat">
                        @foreach ($sunat as $codigo => $nombre)
                            <option value="{{ $codigo }}">{{ $nombre }}</option>
                        @endforeach
                    </datalist>
                </div>
            </fieldset>
            <div class="mt-5 flex justify-end gap-2 border-t border-slate-100 pt-4">
                <a href="{{ route('unidades.index') }}" class="btn btn-secondary">{{ $bloqueado ? 'Volver' : 'Cancelar' }}</a>
                @unless ($bloqueado)
                    <button type="submit" class="btn btn-primary"><x-icon name="save" /> Guardar</button>
                @endunless
            </div>
        </x-card>
    </form>
@endsection
