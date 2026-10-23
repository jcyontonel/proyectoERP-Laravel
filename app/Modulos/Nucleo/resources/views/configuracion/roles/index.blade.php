{{-- RolController@index: $roles (permissions, users_count en esta empresa), $grupos [grupo => [permiso => [etiqueta, descripción]]] --}}
@extends('layouts.app')

@section('title', 'Roles y permisos')

@section('content')
    <x-page-header title="Roles y permisos" :migas="['Configuración' => null, 'Usuarios' => route('usuarios.index'), 'Roles' => null]"
        subtitle="Los roles del sistema son iguales en todas las empresas. Para ajustar permisos, duplica uno y edita la copia: será un rol propio de esta empresa.">
        <x-slot:acciones>
            <a href="{{ route('usuarios.index') }}" class="btn btn-secondary"><x-icon name="users" class="text-brand-600" /> Usuarios</a>
            <a href="{{ route('roles.create') }}" class="btn btn-primary"><x-icon name="plus" /> Nuevo rol</a>
        </x-slot:acciones>
    </x-page-header>

    <x-flash />

    <section class="mt-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
        @foreach ($roles as $r)
            @php $propio = $r->empresa_id !== null; @endphp
            <article class="flex flex-col rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        <h2 class="truncate text-base font-semibold">{{ $r->name }}</h2>
                        <p class="text-xs text-slate-500">{{ $r->users_count }} {{ $r->users_count === 1 ? 'usuario' : 'usuarios' }} en esta empresa</p>
                    </div>
                    <x-badge :tone="$propio ? 'info' : 'neutral'" shape="box">{{ $propio ? 'Propio' : 'Sistema' }}</x-badge>
                </div>
                <p class="mt-3 text-xs text-slate-500">{{ $r->permissions->count() }} permisos</p>
                <div class="mt-2 flex flex-wrap gap-1">
                    @foreach ($grupos as $grupo => $permisos)
                        @php $n = $r->permissions->whereIn('name', array_keys($permisos))->count(); @endphp
                        @if ($n > 0)
                            <span class="rounded-md bg-slate-100 px-2 py-0.5 text-[11px] text-slate-600">{{ $grupo }} · {{ $n }}/{{ count($permisos) }}</span>
                        @endif
                    @endforeach
                </div>
                <div class="min-h-4 flex-1"></div>
                <div class="flex items-center justify-between gap-2 border-t border-slate-100 pt-3 text-xs font-semibold">
                    <a href="{{ route('roles.edit', $r->id) }}" class="text-brand-600 hover:text-brand-700">{{ $propio ? 'Editar permisos' : 'Ver permisos' }}</a>
                    <span class="flex gap-3">
                        <a href="{{ route('roles.create', ['desde' => $r->id]) }}" class="text-brand-600 hover:text-brand-700">Duplicar</a>
                        @if ($propio && $r->users_count === 0)
                            <button type="button" data-eliminar="{{ route('roles.destroy', $r->id) }}" data-nombre="{{ $r->name }}" class="text-red-500 hover:text-red-600">Eliminar</button>
                        @endif
                    </span>
                </div>
            </article>
        @endforeach
    </section>

    <x-dialogo-eliminar titulo="Eliminar rol" mensaje="Solo se eliminan roles propios sin usuarios asignados." />
@endsection
