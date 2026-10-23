{{-- EmpresaController@edit: $e (empresa del ERP) --}}
@extends('plataforma::layouts.app')

@php $nombre = $e['nombre_comercial'] ?: $e['razon_social']; @endphp

@section('title', 'Editar ' . $nombre)

@section('content')
    <x-plataforma::page-header title="Editar empresa" :migas="['Empresas' => route('plataforma.empresas.index'), $nombre => route('plataforma.empresas.show', $e['id']), 'Editar' => null]" />

    <x-plataforma::flash />

    <form method="POST" action="{{ route('plataforma.empresas.update', $e['id']) }}" class="mt-6 max-w-3xl">
        @csrf @method('PUT')
        <x-plataforma::card title="Datos de la empresa" icon="building">
            <div class="grid gap-4 sm:grid-cols-2">
                <x-plataforma::input name="ruc" label="RUC *" :value="$e['ruc']" required maxlength="11" inputmode="numeric" />
                <x-plataforma::input name="razon_social" label="Razón social *" :value="$e['razon_social']" required maxlength="255" />
                <x-plataforma::input name="nombre_comercial" label="Nombre comercial" :value="$e['nombre_comercial']" maxlength="255" />
                <x-plataforma::input name="direccion" label="Dirección" :value="$e['direccion']" maxlength="255" />
                <x-plataforma::input name="departamento" label="Departamento" :value="$e['departamento']" maxlength="100" />
                <x-plataforma::input name="provincia" label="Provincia" :value="$e['provincia']" maxlength="100" />
                <x-plataforma::input name="distrito" label="Distrito" :value="$e['distrito']" maxlength="100" />
                <x-plataforma::input name="ubigeo" label="Ubigeo" :value="$e['ubigeo']" maxlength="6" inputmode="numeric" />
                <x-plataforma::input name="telefono" label="Teléfono" :value="$e['telefono']" maxlength="30" />
                <x-plataforma::input name="email" label="Correo" type="email" :value="$e['email']" maxlength="255" />
            </div>
            <div class="mt-5 border-t border-slate-100 pt-4">
                <x-plataforma::toggle name="activo" label="Empresa activa" :checked="$e['activo']" hint="Una empresa desactivada no aparece para sus usuarios en el ERP (no pueden entrar a ella)." />
            </div>
            <div class="mt-5 flex justify-end gap-2 border-t border-slate-100 pt-4">
                <a href="{{ route('plataforma.empresas.show', $e['id']) }}" class="btn btn-secondary">Cancelar</a>
                <button type="submit" class="btn btn-primary"><x-plataforma::icon name="save" /> Guardar</button>
            </div>
        </x-plataforma::card>
    </form>
@endsection
