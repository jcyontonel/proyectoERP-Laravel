{{-- PerfilController@preferencias: $pref (PreferenciaUsuario), $empresas, $alertas [clave => título] --}}
@extends('layouts.app')

@section('title', 'Preferencias')

@section('content')
    <x-page-header title="Preferencias" :migas="['Mi cuenta' => route('perfil.edit'), 'Preferencias' => null]"
        subtitle="Ajustes personales: valen para ti en todas tus empresas y no afectan a los demás usuarios." />

    <x-flash />

    <form method="POST" action="{{ route('perfil.preferencias.guardar') }}" class="mt-6 max-w-3xl space-y-5">
        @csrf
        @method('PUT')

        @if ($empresas->count() > 1)
            <x-card title="Empresa al iniciar sesión" icon="store">
                <x-select name="empresa_inicio_id" label="Empresa con la que empiezas">
                    <option value="">La primera de mi lista</option>
                    @foreach ($empresas as $e)
                        <option value="{{ $e->id }}" @selected((string) old('empresa_inicio_id', $pref->empresa_inicio_id) === (string) $e->id)>{{ $e->nombre_comercial ?: $e->razon_social }}</option>
                    @endforeach
                </x-select>
                <p class="mt-2 text-[11px] text-slate-500">Puedes cambiar de empresa en cualquier momento desde el menú lateral.</p>
            </x-card>
        @endif

        <x-card title="Impresión de tickets" icon="printer">
            <div class="grid gap-2 sm:grid-cols-2" role="radiogroup" aria-label="Ancho del papel">
                @foreach (['80' => ['80 mm', 'Ticketera estándar (la más común).'], '58' => ['58 mm', 'Ticketeras pequeñas o portátiles.']] as $valor => [$titulo, $texto])
                    <label class="flex cursor-pointer items-start gap-3 rounded-lg border border-slate-200 p-3 has-checked:border-brand-500 has-checked:bg-brand-50">
                        <input type="radio" name="ticket_ancho" value="{{ $valor }}" class="mt-0.5 accent-brand-600" @checked(old('ticket_ancho', $pref->ticket_ancho) === $valor)>
                        <span><span class="block text-sm font-medium">Papel de {{ $titulo }}</span><span class="text-[11px] text-slate-500">{{ $texto }}</span></span>
                    </label>
                @endforeach
            </div>
            <div class="mt-4 border-t border-slate-100 pt-4">
                <x-toggle name="imprimir_al_vender" label="Imprimir el ticket al registrar una venta" :checked="$pref->imprimir_al_vender"
                    hint="Al guardar la venta se abre directamente el cuadro de impresión del ticket." />
            </div>
        </x-card>

        <x-card title="Alertas" icon="bell" subtitle="Elige qué avisos ver en la campana. Solo se muestran los que corresponden a tus permisos.">
            <div class="grid gap-2 sm:grid-cols-2">
                @foreach ($alertas as $clave => $titulo)
                    <label class="flex cursor-pointer items-center gap-3 rounded-lg border border-slate-200 px-3 py-2.5 text-sm has-checked:border-brand-500 has-checked:bg-brand-50/60">
                        <input type="checkbox" name="alertas[]" value="{{ $clave }}" class="size-4 accent-brand-600" @checked(! $pref->ocultaAlerta($clave))>
                        {{ $titulo }}
                    </label>
                @endforeach
            </div>
        </x-card>

        <div class="flex justify-end gap-2">
            <a href="{{ route('perfil.edit') }}" class="btn btn-secondary">Volver</a>
            <button type="submit" class="btn btn-primary"><x-icon name="save" /> Guardar preferencias</button>
        </div>
    </form>
@endsection
