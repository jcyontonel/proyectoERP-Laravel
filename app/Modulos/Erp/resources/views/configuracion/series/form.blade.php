{{-- SerieController@create / edit: $serie (Correlativo), $tipos (Correlativo::TIPOS), $modulos --}}
@extends('layouts.app')

@php
    $editando = $serie->exists;
    $fija = $editando && ! $serie->sinUso();   // ya emitió: módulo, tipo y serie no cambian
    $modulo = old('modulo', $serie->modulo);
    $tipo = old('tipo', $serie->tipo);
@endphp

@section('title', $editando ? 'Serie ' . $serie->serie : 'Nueva serie')

@section('content')
    <x-page-header :title="$editando ? 'Serie ' . $serie->serie : 'Nueva serie'"
        :migas="['Configuración' => null, 'Series y correlativos' => route('series.index'), ($editando ? $serie->serie : 'Nueva') => null]" />

    <x-flash />

    <form method="POST" action="{{ $editando ? route('series.update', $serie) : route('series.store') }}" class="mt-6 max-w-2xl" id="form-serie">
        @csrf
        @if ($editando) @method('PUT') @endif

        <x-card title="Datos de la serie" icon="hash">
            @if ($fija)
                <x-alert tone="info" class="mb-4">Esta serie ya emitió documentos hasta el número {{ \App\Modulos\Erp\Models\Correlativo::formatear($serie->numero) }}: solo puedes cambiar su descripción, adelantar el número o desactivarla.</x-alert>
            @endif

            <fieldset @disabled($fija) class="grid gap-4 sm:grid-cols-2">
                <x-select name="modulo" label="Módulo *" data-modulo>
                    @foreach ($modulos as $valor => $etiqueta)
                        <option value="{{ $valor }}" @selected($modulo === $valor)>{{ $etiqueta }}</option>
                    @endforeach
                </x-select>
                <x-select name="tipo" label="Tipo de comprobante *" data-tipo>
                    @foreach ($tipos as $mod => $lista)
                        @foreach ($lista as $codigo => [$descripcion, $patron, $ejemplo])
                            <option value="{{ $codigo }}" data-de="{{ $mod }}" data-ejemplo="{{ $ejemplo }}" data-descripcion="{{ $descripcion }}"
                                @selected($tipo === $codigo && $modulo === $mod) @if ($modulo !== $mod) hidden disabled @endif>{{ $descripcion }} ({{ $codigo }})</option>
                        @endforeach
                    @endforeach
                </x-select>
                <x-input name="serie" label="Serie *" :value="$serie->serie" required maxlength="10" class="font-mono uppercase" data-serie
                    :placeholder="$tipos[$modulo][$tipo][2] ?? 'F001'" hint="Facturas: F + 3 caracteres (F001). Boletas: B + 3 (B001). Notas: la letra del comprobante que modifican (FC01, BC01)." />
            </fieldset>

            <div class="mt-4 grid gap-4 sm:grid-cols-2">
                <x-input name="destipo" label="Descripción *" :value="$serie->destipo ?: ($tipos[$modulo][$tipo][0] ?? '')" required maxlength="30" data-destipo />
                <x-input name="numero" label="Último número emitido" type="number" min="{{ (int) $serie->numero }}" :value="(int) $serie->numero"
                    hint="Si migras desde otro sistema, indica el último número que emitiste. Nunca puede retroceder." />
            </div>
            <p class="mt-3 rounded-lg bg-slate-50 px-3 py-2 text-xs text-slate-600">Próximo documento: <strong class="font-mono text-ink" data-proximo>{{ ($serie->serie ?: '—') }}-{{ $serie->siguiente() }}</strong></p>

            <div class="mt-5 border-t border-slate-100 pt-4">
                <x-toggle name="activo" label="Serie activa" :checked="$serie->activo" hint="Las series inactivas no aparecen al registrar documentos." />
            </div>

            <div class="mt-5 flex justify-end gap-2 border-t border-slate-100 pt-4">
                <a href="{{ route('series.index') }}" class="btn btn-secondary">Cancelar</a>
                <button type="submit" class="btn btn-primary"><x-icon name="save" /> Guardar</button>
            </div>
        </x-card>
    </form>
@endsection

@push('scripts')
    <script>
        (() => {
            const form = document.getElementById('form-serie');
            const modulo = form.querySelector('[data-modulo]');
            const tipo = form.querySelector('[data-tipo]');
            const serie = form.querySelector('[data-serie]');
            const destipo = form.querySelector('[data-destipo]');
            const pad = {{ (int) config('ventas.numero_pad', 6) }};

            const proximo = () => {
                const n = (parseInt(form.numero.value, 10) || 0) + 1;
                form.querySelector('[data-proximo]').textContent = (serie.value.toUpperCase() || '—') + '-' + String(n).padStart(pad, '0');
            };

            // Solo los tipos del módulo elegido; la descripción y el ejemplo siguen al tipo
            modulo.addEventListener('change', () => {
                let primero = null;
                [...tipo.options].forEach((o) => {
                    const visible = o.dataset.de === modulo.value;
                    o.hidden = o.disabled = !visible;
                    if (visible && !primero) primero = o;
                });
                tipo.value = primero.value;
                tipo.dispatchEvent(new Event('change'));
            });
            tipo.addEventListener('change', () => {
                const o = tipo.selectedOptions[0];
                serie.placeholder = o.dataset.ejemplo;
                destipo.value = o.dataset.descripcion;
            });
            form.addEventListener('input', proximo);
        })();
    </script>
@endpush
