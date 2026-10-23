{{-- PlanController@create / edit: $plan (array; nuevo = sin id) --}}
@extends('plataforma::layouts.app')

@php
    $editando = isset($plan['id']);
    $precios = collect($plan['precios'] ?? [])->keyBy('periodicidad');
@endphp

@section('title', $editando ? 'Editar ' . $plan['nombre'] : 'Nuevo plan')

@section('content')
    <x-plataforma::page-header :title="$editando ? 'Editar plan ' . $plan['nombre'] : 'Nuevo plan'" :migas="['Planes' => route('plataforma.planes.index'), ($editando ? $plan['nombre'] : 'Nuevo') => null]" />

    <x-plataforma::flash />

    <form method="POST" action="{{ $editando ? route('plataforma.planes.update', $plan['id']) : route('plataforma.planes.store') }}" class="mt-6 grid max-w-5xl gap-5 lg:grid-cols-2">
        @csrf
        @if ($editando) @method('PUT') @endif

        <x-plataforma::card title="Plan" icon="layers">
            <div class="grid gap-4 sm:grid-cols-[10rem_minmax(0,1fr)]">
                <x-plataforma::input name="codigo" label="Código *" :value="$plan['codigo'] ?? ''" required maxlength="30" class="font-mono" placeholder="premium" />
                <x-plataforma::input name="nombre" label="Nombre *" :value="$plan['nombre'] ?? ''" required maxlength="60" />
                <div class="sm:col-span-2"><x-plataforma::input name="descripcion" label="Descripción" :value="$plan['descripcion'] ?? ''" maxlength="255" /></div>
                <x-plataforma::input name="dias_prueba" label="Días de prueba" type="number" min="0" max="90" :value="$plan['dias_prueba'] ?? 0" />
                <x-plataforma::input name="orden" label="Orden" type="number" min="0" :value="$plan['orden'] ?? 0" />
            </div>
            <div class="mt-5 border-t border-slate-100 pt-4">
                <x-plataforma::toggle name="activo" label="Plan activo" :checked="$plan['activo'] ?? true" hint="Desactivado: no se puede asignar a empresas (las que ya lo tienen lo conservan)." />
            </div>
        </x-plataforma::card>

        <div class="space-y-5">
            <x-plataforma::card title="Límites" icon="sliders" subtitle="Deja vacío para ilimitado.">
                <div class="grid grid-cols-2 gap-4">
                    <x-plataforma::input name="max_comprobantes_mes" label="Comprobantes al mes" type="number" min="0" :value="$plan['max_comprobantes_mes'] ?? ''" placeholder="Ilimitado" />
                    <x-plataforma::input name="max_productos" label="Productos" type="number" min="0" :value="$plan['max_productos'] ?? ''" placeholder="Ilimitado" />
                    <x-plataforma::input name="max_usuarios" label="Usuarios" type="number" min="1" :value="$plan['max_usuarios'] ?? ''" placeholder="Ilimitado" />
                    <x-plataforma::input name="max_almacenes" label="Almacenes" type="number" min="1" :value="$plan['max_almacenes'] ?? ''" placeholder="Ilimitado" />
                </div>
            </x-plataforma::card>

            <x-plataforma::card title="Precios" icon="coins" subtitle="Deja vacío si el plan no se ofrece en esa periodicidad.">
                <div class="grid grid-cols-3 gap-4">
                    @foreach (config('plataforma.periodicidades') as $clave => $etiqueta)
                        @php $pr = $precios[$clave] ?? null; @endphp
                        <x-plataforma::input name="precios[{{ $clave }}]" id="precio-{{ $clave }}" :label="$etiqueta . ' (S/)'" type="number" step="0.01" min="0"
                            :value="old('precios.' . $clave, $pr && $pr['activo'] ? $pr['precio'] : '')" />
                    @endforeach
                </div>
                @error('precios')<p class="mt-2 text-xs text-red-600">{{ $message }}</p>@enderror
            </x-plataforma::card>

            {{-- Módulos que habilita el plan en el ERP: los no marcados no aparecen en el menú ni se pueden abrir --}}
            <x-plataforma::card title="Módulos incluidos" icon="list-checks" subtitle="Ventas, caja, productos, clientes, facturación electrónica y configuración van en todos los planes.">
                @php $marcados = old('modulos', $plan['modulos'] ?? []); @endphp
                <input type="hidden" name="modulos_enviados" value="1">
                <div class="grid gap-2 sm:grid-cols-2">
                    @foreach ($modulos as $m)
                        <label class="flex items-start gap-2 rounded-lg border border-slate-200 p-2.5 text-sm hover:bg-slate-50">
                            <input type="checkbox" name="modulos[]" value="{{ $m['clave'] }}" @checked(in_array($m['clave'], $marcados, true)) class="mt-0.5 accent-brand-600">
                            <span><span class="font-medium">{{ $m['nombre'] }}</span><span class="block text-[11px] text-slate-500">{{ $m['descripcion'] }}</span></span>
                        </label>
                    @endforeach
                </div>
                @error('modulos')<p class="mt-2 text-xs text-red-600">{{ $message }}</p>@enderror
            </x-plataforma::card>

            <div class="flex justify-end gap-2">
                <a href="{{ route('plataforma.planes.index') }}" class="btn btn-secondary">Cancelar</a>
                <button type="submit" class="btn btn-primary"><x-plataforma::icon name="save" /> Guardar plan</button>
            </div>
        </div>
    </form>
@endsection
