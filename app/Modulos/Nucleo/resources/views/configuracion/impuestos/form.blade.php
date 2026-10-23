{{-- ImpuestoController@create / edit: $impuesto, $afectaciones (las disponibles), $bloqueado (otra empresa la usa) --}}
@extends('layouts.app')

@php $editando = $impuesto->exists; @endphp

@section('title', $editando ? $impuesto->nombre : 'Nueva afectación')

@section('content')
    <x-page-header :title="$editando ? $impuesto->nombre : 'Nueva afectación'"
        :migas="['Configuración' => null, 'Impuestos' => route('impuestos.index'), ($editando ? $impuesto->codigo : 'Nueva') => null]" />

    <x-flash />

    @if ($bloqueado)
        <x-alert tone="info" class="mt-6 max-w-2xl">Otras empresas usan esta afectación en sus productos o documentos: se muestra en solo lectura.</x-alert>
    @endif

    <form method="POST" action="{{ $editando ? route('impuestos.update', $impuesto) : route('impuestos.store') }}" class="mt-6 max-w-2xl" id="form-impuesto">
        @csrf
        @if ($editando) @method('PUT') @endif

        <x-card title="Afectación al IGV" icon="percent">
            @if ($afectaciones->isEmpty())
                <p class="text-sm text-slate-500">Ya están registradas todas las afectaciones del catálogo SUNAT 07.</p>
            @else
                <fieldset @disabled($bloqueado) class="space-y-4">
                    <x-select name="tipo_afectacion" label="Tipo de afectación (catálogo 07) *" :disabled="$editando" data-afectacion>
                        @foreach ($afectaciones as $codigo => [$descripcion, $tributo, $conTasa])
                            <option value="{{ $codigo }}" data-descripcion="{{ $descripcion }}" data-tasa="{{ $conTasa ? 1 : 0 }}" @selected(old('tipo_afectacion', $impuesto->tipo_afectacion) === $codigo)>
                                {{ $codigo }} · {{ $descripcion }} (tributo {{ $tributo }})
                            </option>
                        @endforeach
                    </x-select>
                    <div class="grid gap-4 sm:grid-cols-[10rem_minmax(0,1fr)_8rem]">
                        <x-input name="codigo" label="Código *" :value="$impuesto->codigo" maxlength="10" :readonly="$editando" class="font-mono uppercase" placeholder="IVAP" />
                        <x-input name="nombre" label="Nombre *" :value="$impuesto->nombre" maxlength="60" required data-nombre />
                        <x-input name="porcentaje" label="Tasa (%)" type="number" step="0.01" min="0" max="100" :value="(float) $impuesto->porcentaje" data-tasa />
                    </div>
                    <p class="text-[11px] text-slate-500">La tasa solo aplica a las afectaciones gravadas (10–17). Exonerado, inafecto y exportación van con 0 %.</p>
                    <div class="border-t border-slate-100 pt-4">
                        <x-toggle name="activo" label="Activa" :checked="$impuesto->activo" hint="Una afectación inactiva no se puede elegir en productos nuevos." />
                    </div>
                </fieldset>
            @endif

            <div class="mt-5 flex justify-end gap-2 border-t border-slate-100 pt-4">
                <a href="{{ route('impuestos.index') }}" class="btn btn-secondary">{{ $bloqueado ? 'Volver' : 'Cancelar' }}</a>
                @unless ($bloqueado || $afectaciones->isEmpty())
                    <button type="submit" class="btn btn-primary"><x-icon name="save" /> Guardar</button>
                @endunless
            </div>
        </x-card>
    </form>
@endsection

@push('scripts')
    <script>
        (() => {
            const sel = document.querySelector('[data-afectacion]');
            const tasa = document.querySelector('[data-tasa]');
            if (!sel || !tasa) return;
            const pintar = (nuevo) => {
                const o = sel.selectedOptions[0];
                const conTasa = o?.dataset.tasa === '1';
                tasa.readOnly = !conTasa;
                if (!conTasa) tasa.value = 0;
                if (nuevo && o) document.querySelector('[data-nombre]').value = o.dataset.descripcion;
            };
            sel.addEventListener('change', () => pintar(true));
            pintar(false);
        })();
    </script>
@endpush
