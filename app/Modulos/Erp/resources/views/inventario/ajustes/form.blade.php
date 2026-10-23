{{-- Crear / editar ajuste: $ajuste, $almacenes, $motivos, $productos (CatalogoInventario) --}}
@extends('layouts.app')

@php
    $editando = $ajuste->exists;
    $titulo = $editando ? 'Editar ajuste ' . $ajuste->numero_completo : 'Nuevo ajuste de inventario';
    $iniciales = old('productos', $editando ? $ajuste->detalles->map(fn ($d) => [
        'id'       => $d->producto_id,
        'cantidad' => $ajuste->tipo === 'conteo' ? $d->stock_contado : $d->cantidad,
        'lote'     => $d->lote?->codigo_lote,
        'vence'    => $d->lote?->fecha_vencimiento?->format('Y-m-d'),
    ])->all() : []);
    $almSel = old('almacen_id', $ajuste->almacen_id ?? $almacenes->first()?->id);
@endphp

@section('title', $titulo)

@section('content')
    <x-page-header :title="$titulo" :migas="['Inventario' => null, 'Ajustes' => route('ajustes.index'), ($editando ? $ajuste->numero_completo : 'Nuevo') => null]"
        subtitle="Guárdalo como borrador para revisarlo, o confírmalo para mover el stock. Los motivos con aprobación quedan pendientes hasta que un responsable los autorice." />

    <x-flash />

    <form method="POST" action="{{ $editando ? route('ajustes.update', $ajuste) : route('ajustes.store') }}"
        class="mt-6 grid gap-5 lg:grid-cols-[minmax(0,1fr)_20rem]">
        @csrf
        @if ($editando) @method('PUT') @endif

        @include('inventario.partials.editor-lineas', ['iniciales' => $iniciales, 'almacenSelector' => '#almacen_id', 'tipoSelector' => '#tipo', 'class' => 'lg:row-span-2'])

        <aside class="space-y-5 lg:sticky lg:top-24 lg:self-start">
            <x-card title="Datos del ajuste" icon="sliders">
                <div class="space-y-4">
                    <x-select name="tipo" label="Tipo de ajuste *">
                        @foreach (\App\Modulos\Erp\Models\AjusteInventario::TIPOS as $v => $e)
                            <option value="{{ $v }}" @selected(old('tipo', $ajuste->tipo) === $v)>{{ $e }}</option>
                        @endforeach
                    </x-select>
                    <p data-ayuda-tipo class="-mt-2 text-[11px] text-slate-500"></p>
                    <x-select name="almacen_id" label="Almacén *">
                        @foreach ($almacenes as $a)
                            <option value="{{ $a->id }}" @selected((string) $almSel === (string) $a->id)>{{ $a->nombre }}</option>
                        @endforeach
                    </x-select>
                    <x-select name="motivo_ajuste_id" label="Motivo *">
                        <option value="">Selecciona…</option>
                        @foreach ($motivos as $m)
                            <option value="{{ $m->id }}" data-tipo="{{ $m->tipo_movimiento }}" @selected((string) old('motivo_ajuste_id', $ajuste->motivo_ajuste_id) === (string) $m->id)>
                                {{ $m->nombre }}{{ $m->requiere_aprobacion ? ' · requiere aprobación' : '' }}
                            </option>
                        @endforeach
                    </x-select>
                    <x-input name="fecha_movimiento" label="Fecha *" type="date" :value="old('fecha_movimiento', $ajuste->fecha_movimiento?->format('Y-m-d'))" max="{{ now()->format('Y-m-d') }}" />
                    <x-textarea name="observacion" label="Observación" :value="$ajuste->observacion" rows="2" maxlength="1000" />
                </div>
            </x-card>

            <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5">
                <button type="submit" name="accion" value="confirmar" class="btn btn-primary w-full"><x-icon name="check" /> Confirmar ajuste</button>
                <button type="submit" name="accion" value="borrador" class="btn btn-secondary mt-2 w-full"><x-icon name="save" class="text-brand-600" /> Guardar borrador</button>
                <a href="{{ $editando ? route('ajustes.show', $ajuste) : route('ajustes.index') }}" class="mt-3 block text-center text-xs text-slate-500 hover:text-ink">Cancelar</a>
            </div>
        </aside>
    </form>
@endsection

@push('scripts')
    <script>
        (() => {
            // Ayuda del tipo y motivos compatibles (ingreso: ingreso/ambos · salida: egreso/ambos · conteo: ambos)
            const tipo = document.getElementById('tipo');
            const motivo = document.getElementById('motivo_ajuste_id');
            const ayuda = document.querySelector('[data-ayuda-tipo]');
            const textos = {
                ingreso: 'Suma las cantidades al stock del almacén (sobrantes, inventario inicial).',
                egreso: 'Resta las cantidades del stock (mermas, vencidos, consumo interno).',
                conteo: 'Escribe lo que contaste: al confirmar se ajusta la diferencia con el stock de ese momento.',
            };
            const permitidos = { ingreso: ['ingreso', 'ambos'], egreso: ['egreso', 'ambos'], conteo: ['ambos'] };
            const pintar = () => {
                ayuda.textContent = textos[tipo.value] ?? '';
                [...motivo.options].forEach((o) => { if (o.value) o.hidden = !permitidos[tipo.value].includes(o.dataset.tipo); });
                if (motivo.selectedOptions[0]?.hidden) motivo.value = '';
            };
            tipo.addEventListener('change', pintar);
            pintar();
        })();
    </script>
@endpush
