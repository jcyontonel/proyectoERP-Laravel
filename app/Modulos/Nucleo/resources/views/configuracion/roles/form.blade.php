{{--
    RolController@create / edit
      $rol       Role (nuevo o existente)
      $marcados  nombres de permisos marcados
      $grupos    [grupo => [permiso => [etiqueta, descripción]]]
      $soloVer   rol del sistema: solo lectura
--}}
@extends('layouts.app')

@php
    $editando = $rol->exists;
    $marcados = old('permisos', $marcados);
    $titulo = $soloVer ? $rol->name : ($editando ? 'Editar rol' : 'Nuevo rol');
@endphp

@section('title', $titulo)

@section('content')
    <x-page-header :title="$titulo" :migas="['Configuración' => null, 'Usuarios' => route('usuarios.index'), 'Roles' => route('roles.index'), ($editando ? $rol->name : 'Nuevo') => null]"
        :subtitle="$soloVer ? 'Rol del sistema: sus permisos no se modifican. Duplícalo para crear una versión propia.' : 'Marca lo que podrán hacer los usuarios con este rol en esta empresa.'">
        @if ($soloVer)
            <x-slot:acciones>
                <a href="{{ route('roles.create', ['desde' => $rol->id]) }}" class="btn btn-primary"><x-icon name="layers" /> Duplicar rol</a>
            </x-slot:acciones>
        @endif
    </x-page-header>

    <x-flash />

    <form method="POST" action="{{ $editando ? route('roles.update', $rol->id) : route('roles.store') }}" class="mt-6 max-w-4xl space-y-5">
        @csrf
        @if ($editando) @method('PUT') @endif

        <fieldset @disabled($soloVer) class="space-y-5">
            <x-card title="Nombre del rol" icon="shield">
                <x-input name="name" label="Nombre *" :value="$rol->name" required maxlength="60" placeholder="Cajero, Supervisor de tienda…" />
            </x-card>

            <x-card title="Permisos" icon="list-checks">
                <x-slot:aside>
                    @unless ($soloVer)
                        <button type="button" data-todos class="font-semibold text-brand-600 hover:text-brand-700">Marcar todos</button>
                        · <button type="button" data-ninguno class="font-semibold text-brand-600 hover:text-brand-700">Ninguno</button>
                    @endunless
                </x-slot:aside>

                <div class="space-y-6">
                    @foreach ($grupos as $grupo => $permisos)
                        <div>
                            <h3 class="mb-2 text-xs font-semibold uppercase tracking-wide text-slate-500">{{ $grupo }}</h3>
                            <div class="grid gap-2 sm:grid-cols-2">
                                @foreach ($permisos as $permiso => [$etiqueta, $descripcion])
                                    <label class="flex cursor-pointer items-start gap-3 rounded-lg border border-slate-200 p-3 has-checked:border-brand-500 has-checked:bg-brand-50/60">
                                        <input type="checkbox" name="permisos[]" value="{{ $permiso }}" class="mt-0.5 size-4 accent-brand-600" @checked(in_array($permiso, $marcados, true))>
                                        <span>
                                            <span class="block text-sm font-medium">{{ $etiqueta }}</span>
                                            @if ($descripcion)<span class="block text-[11px] leading-snug text-slate-500">{{ $descripcion }}</span>@endif
                                        </span>
                                    </label>
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                </div>
                @error('permisos.*')<p class="mt-2 text-xs text-red-600">{{ $message }}</p>@enderror
            </x-card>
        </fieldset>

        <div class="flex justify-end gap-2">
            <a href="{{ route('roles.index') }}" class="btn btn-secondary">{{ $soloVer ? 'Volver' : 'Cancelar' }}</a>
            @unless ($soloVer)
                <button type="submit" class="btn btn-primary"><x-icon name="save" /> Guardar rol</button>
            @endunless
        </div>
    </form>
@endsection

@push('scripts')
    <script>
        document.addEventListener('click', (e) => {
            const marcar = e.target.closest('[data-todos]') ? true : e.target.closest('[data-ninguno]') ? false : null;
            if (marcar === null) return;
            document.querySelectorAll('input[name="permisos[]"]').forEach((c) => { c.checked = marcar; });
        });
    </script>
@endpush
