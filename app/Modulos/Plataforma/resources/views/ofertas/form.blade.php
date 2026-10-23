{{-- OfertaController@create / edit: $oferta (array; nueva = sin id), $planes --}}
@extends('plataforma::layouts.app')

@php $editando = isset($oferta['id']); @endphp

@section('title', $editando ? 'Editar oferta' : 'Nueva oferta')

@section('content')
    <x-plataforma::page-header :title="$editando ? 'Editar oferta' : 'Nueva oferta'" :migas="['Ofertas' => route('plataforma.ofertas.index'), ($editando ? $oferta['nombre'] : 'Nueva') => null]" />

    <x-plataforma::flash />

    <form method="POST" action="{{ $editando ? route('plataforma.ofertas.update', $oferta['id']) : route('plataforma.ofertas.store') }}" class="mt-6 max-w-3xl">
        @csrf
        @if ($editando) @method('PUT') @endif

        <x-plataforma::card title="Oferta" icon="tag">
            <div class="grid gap-4 sm:grid-cols-2">
                <x-plataforma::input name="nombre" label="Nombre *" :value="$oferta['nombre'] ?? ''" required maxlength="80" placeholder="Lanzamiento, Cyber Wow…" />
                <x-plataforma::input name="codigo" label="Código de cupón (opcional)" :value="$oferta['codigo'] ?? ''" maxlength="30" class="font-mono uppercase"
                    hint="Para aplicarla, al asignar el plan escribe este código o el número de la oferta." />
                <x-plataforma::select name="tipo" label="Tipo de descuento">
                    <option value="porcentaje" @selected(old('tipo', $oferta['tipo'] ?? '') === 'porcentaje')>Porcentaje (%)</option>
                    <option value="monto" @selected(old('tipo', $oferta['tipo'] ?? '') === 'monto')>Monto fijo (S/)</option>
                </x-plataforma::select>
                <x-plataforma::input name="valor" label="Valor *" type="number" step="0.01" min="0.01" :value="$oferta['valor'] ?? ''" required />
                <x-plataforma::select name="plan_id" label="Plan">
                    <option value="">Todos los planes</option>
                    @foreach ($planes as $p)<option value="{{ $p['id'] }}" @selected((string) old('plan_id', $oferta['plan_id'] ?? '') === (string) $p['id'])>{{ $p['nombre'] }}</option>@endforeach
                </x-plataforma::select>
                <x-plataforma::select name="periodicidad" label="Periodicidad">
                    <option value="">Cualquiera</option>
                    @foreach (config('plataforma.periodicidades') as $v => $et)<option value="{{ $v }}" @selected(old('periodicidad', $oferta['periodicidad'] ?? '') === $v)>{{ $et }}</option>@endforeach
                </x-plataforma::select>
                <x-plataforma::input name="desde" label="Desde *" type="date" :value="$oferta['desde'] ?? ''" required />
                <x-plataforma::input name="hasta" label="Hasta" type="date" :value="$oferta['hasta'] ?? ''" hint="Vacío: sin fecha de fin." />
                <x-plataforma::input name="max_usos" label="Máximo de usos" type="number" min="1" :value="$oferta['max_usos'] ?? ''" hint="Vacío: sin límite. Cuenta las empresas que la usaron." />
            </div>
            <div class="mt-5 space-y-4 border-t border-slate-100 pt-4">
                <x-plataforma::toggle name="solo_primer_pago" label="Solo el primer pago" :checked="$oferta['solo_primer_pago'] ?? true" hint="Si lo desactivas, el descuento se repite en cada renovación." />
                <x-plataforma::toggle name="activo" label="Activa" :checked="$oferta['activo'] ?? true" />
            </div>
            <div class="mt-5 flex justify-end gap-2 border-t border-slate-100 pt-4">
                <a href="{{ route('plataforma.ofertas.index') }}" class="btn btn-secondary">Cancelar</a>
                <button type="submit" class="btn btn-primary"><x-plataforma::icon name="save" /> Guardar</button>
            </div>
        </x-plataforma::card>
    </form>
@endsection
