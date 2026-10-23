{{--
    UsuarioController@create / edit
      $usuario     User (nuevo o existente)
      $roles       roles disponibles (sistema + propios)
      $rolActual   Role|null en esta empresa
      $editaDatos  bool: solo trabaja en esta empresa → se editan nombre, correo, contraseña y estado
--}}
@extends('layouts.app')

@php
    $editando = $usuario->exists;
    $esYo = $editando && $usuario->is(auth()->user());
@endphp

@section('title', $editando ? 'Editar usuario' : 'Nuevo usuario')

@section('content')
    <x-page-header :title="$editando ? $usuario->nombre_completo : 'Nuevo usuario'"
        :migas="['Configuración' => null, 'Usuarios' => route('usuarios.index'), ($editando ? 'Editar' : 'Nuevo') => null]"
        :subtitle="$editando ? $usuario->email : 'Si el correo ya tiene cuenta en el sistema, solo se le da acceso a esta empresa con el rol elegido.'" />

    <x-flash />

    <form method="POST" action="{{ $editando ? route('usuarios.update', $usuario) : route('usuarios.store') }}" class="mt-6 grid max-w-5xl gap-5 lg:grid-cols-[minmax(0,1fr)_22rem]">
        @csrf
        @if ($editando) @method('PUT') @endif

        <x-card title="Datos personales" icon="user">
            @unless ($editaDatos)
                <x-alert tone="info" class="mb-4">Este usuario también trabaja en otras empresas: sus datos y su contraseña los cambia él desde "Mi perfil". Aquí solo defines su rol en esta empresa.</x-alert>
            @endunless
            <fieldset @disabled(! $editaDatos) class="grid gap-4 sm:grid-cols-2">
                <x-input name="email" label="Correo *" type="email" :value="$usuario->email" required maxlength="255" autocomplete="off" />
                <x-input name="telefono" label="Teléfono" type="tel" :value="$usuario->telefono" maxlength="30" />
                <x-input name="name" :label="$editando ? 'Nombres *' : 'Nombres'" :value="$usuario->name" maxlength="100" :required="$editando" />
                <x-input name="apellido" label="Apellidos" :value="$usuario->apellido" maxlength="100" />
            </fieldset>
            @unless ($editaDatos)
                {{-- El fieldset deshabilitado no envía: se reenvían los datos actuales --}}
                @foreach (['email', 'name', 'apellido', 'telefono'] as $campo)
                    <input type="hidden" name="{{ $campo }}" value="{{ $usuario->{$campo} }}">
                @endforeach
            @endunless

            @if ($editaDatos)
                <div class="mt-5 border-t border-slate-100 pt-4">
                    <h3 class="text-sm font-semibold">{{ $editando ? 'Cambiar contraseña' : 'Contraseña' }}</h3>
                    <p class="mt-0.5 text-xs text-slate-500">{{ $editando ? 'Déjala vacía para mantener la actual.' : 'Mínimo 8 caracteres con letras y números. No se pide si el correo ya tiene cuenta.' }}</p>
                    <div class="mt-3 grid gap-4 sm:grid-cols-2">
                        <x-input name="password" label="Contraseña" type="password" autocomplete="new-password" />
                        <x-input name="password_confirmation" label="Repetir contraseña" type="password" autocomplete="new-password" />
                    </div>
                </div>
            @endif
        </x-card>

        <div class="space-y-5">
            <x-card title="Rol en esta empresa" icon="shield">
                @if ($esYo)
                    <x-alert tone="info" class="mb-3">No puedes cambiar tu propio rol.</x-alert>
                    <input type="hidden" name="rol_id" value="{{ $rolActual?->id }}">
                @endif
                <div class="space-y-2" role="radiogroup" aria-label="Rol">
                    @foreach ($roles as $r)
                        <label @class(['flex cursor-pointer items-start gap-3 rounded-lg border border-slate-200 p-3 has-checked:border-brand-500 has-checked:bg-brand-50', 'pointer-events-none opacity-60' => $esYo])>
                            <input type="radio" name="rol_id" value="{{ $r->id }}" class="mt-0.5 accent-brand-600" @checked((string) old('rol_id', $rolActual?->id) === (string) $r->id) @disabled($esYo)>
                            <span>
                                <span class="block text-sm font-medium">{{ $r->name }}</span>
                                <span class="text-[11px] text-slate-500">{{ $r->empresa_id ? 'Rol propio de la empresa' : 'Rol del sistema' }}</span>
                            </span>
                        </label>
                    @endforeach
                </div>
                @error('rol_id')<p class="mt-2 text-xs text-red-600">{{ $message }}</p>@enderror
                <a href="{{ route('roles.index') }}" class="mt-3 inline-block text-xs font-semibold text-brand-600 hover:text-brand-700">Ver qué permite cada rol</a>
            </x-card>

            @if ($editando && $editaDatos && ! $esYo)
                <x-card title="Estado" icon="lock">
                    <x-toggle name="activo" label="Usuario activo" :checked="$usuario->activo" hint="Un usuario inactivo no puede iniciar sesión; si tiene la sesión abierta, se cierra." />
                </x-card>
            @endif

            <div class="flex justify-end gap-2">
                <a href="{{ route('usuarios.index') }}" class="btn btn-secondary">Cancelar</a>
                <button type="submit" class="btn btn-primary"><x-icon name="save" /> {{ $editando ? 'Guardar' : 'Crear usuario' }}</button>
            </div>
        </div>
    </form>
@endsection
