{{--
    Crear y editar proveedor.
    Variables: $proveedor (Proveedor, nuevo o existente), $tiposDocumento
--}}
@extends('layouts.app')

@php $editando = $proveedor->exists; @endphp

@section('title', $editando ? 'Editar proveedor' : 'Nuevo proveedor')

@section('content')
    <nav aria-label="Ruta" class="flex items-center gap-2 text-xs text-slate-500">
        <a href="{{ route('proveedores.index') }}" class="hover:text-brand-600">Proveedores</a>
        <x-icon name="chevron-right" class="size-3" />
        <span class="text-ink">{{ $editando ? 'Editar' : 'Nuevo' }}</span>
    </nav>

    <div class="mt-4">
        <h1 class="text-2xl font-bold tracking-tight sm:text-3xl">{{ $editando ? 'Editar proveedor' : 'Nuevo proveedor' }}</h1>
        <p class="mt-1.5 text-sm text-slate-500">Datos del proveedor al que le compras mercadería.</p>
    </div>

    @if ($errors->any())
        <x-alert tone="danger" class="mt-4">Revisa los campos marcados en rojo.</x-alert>
    @endif

    <form method="POST" action="{{ $editando ? route('proveedores.update', $proveedor) : route('proveedores.store') }}" novalidate
        class="mt-6 grid gap-5 lg:grid-cols-2">
        @csrf
        @if ($editando) @method('PUT') @endif

        <x-card title="Identificación" icon="building">
            <div class="grid gap-4 sm:grid-cols-[9rem_minmax(0,1fr)]">
                <x-select name="tipo_documento_id" label="Tipo de documento">
                    <option value="">—</option>
                    @foreach ($tiposDocumento as $t)
                        <option value="{{ $t->id }}" @selected((string) old('tipo_documento_id', $proveedor->tipo_documento_id) === (string) $t->id)>
                            {{ $t->codigo === '6' ? 'RUC' : ($t->codigo === '1' ? 'DNI' : $t->descripcion) }}
                        </option>
                    @endforeach
                </x-select>
                <x-input name="numero_documento" label="Número de documento" :value="$proveedor->numero_documento" maxlength="20" inputmode="numeric" autocomplete="off" />
            </div>
            <div class="mt-4 space-y-4">
                <x-input name="razon_social" label="Razón social" :value="$proveedor->razon_social" maxlength="255" autocomplete="off" />
                <x-input name="nombre_comercial" label="Nombre comercial (opcional)" :value="$proveedor->nombre_comercial" maxlength="255" autocomplete="off" />
            </div>
        </x-card>

        <x-card title="Contacto" icon="user">
            <div class="grid gap-4 sm:grid-cols-2">
                <x-input name="contacto_nombre" label="Persona de contacto" :value="$proveedor->contacto_nombre" maxlength="255" autocomplete="off" />
                <x-input name="contacto_cargo" label="Cargo" :value="$proveedor->contacto_cargo" maxlength="255" autocomplete="off" />
                <x-input name="telefono" label="Teléfono" :value="$proveedor->telefono" maxlength="20" autocomplete="off" />
                <x-input name="celular" label="Celular" :value="$proveedor->celular" maxlength="20" autocomplete="off" />
                <div class="sm:col-span-2">
                    <x-input name="email" label="Correo electrónico" type="email" :value="$proveedor->email" maxlength="255" autocomplete="off" />
                </div>
            </div>
        </x-card>

        <x-card title="Ubicación" icon="building">
            <div class="grid gap-4 sm:grid-cols-2">
                <x-input name="pais" label="País" :value="$proveedor->pais ?: 'Perú'" maxlength="100" />
                <x-input name="departamento" label="Departamento" :value="$proveedor->departamento" maxlength="100" />
                <x-input name="provincia" label="Provincia" :value="$proveedor->provincia" maxlength="100" />
                <x-input name="distrito" label="Distrito" :value="$proveedor->distrito" maxlength="100" />
                <div class="sm:col-span-2">
                    <x-input name="direccion" label="Dirección" :value="$proveedor->direccion" maxlength="255" />
                </div>
            </div>
        </x-card>

        <x-card title="Otros datos" icon="file-text">
            <div>
                <label for="observacion" class="field-label">Observaciones (opcional)</label>
                <textarea id="observacion" name="observacion" rows="4" maxlength="2000"
                    class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm placeholder:text-slate-400 focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-500/20">{{ old('observacion', $proveedor->observacion) }}</textarea>
                @error('observacion') <p class="mt-1.5 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>

            <label class="mt-4 flex cursor-pointer items-center gap-2.5 text-sm">
                <input type="hidden" name="activo" value="0">
                <input type="checkbox" name="activo" value="1" @checked(old('activo', $proveedor->activo ?? true)) class="size-4 rounded border-slate-300 accent-brand-600">
                Proveedor activo (aparece al registrar compras)
            </label>
        </x-card>

        <div class="flex flex-wrap items-center justify-end gap-2 lg:col-span-2">
            <a href="{{ route('proveedores.index') }}" class="btn btn-secondary">Cancelar</a>
            <button type="submit" class="btn btn-primary"><x-icon name="check" /> {{ $editando ? 'Guardar cambios' : 'Crear proveedor' }}</button>
        </div>
    </form>
@endsection
