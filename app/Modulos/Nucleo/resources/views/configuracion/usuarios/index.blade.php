{{--
    UsuarioController@index
      $usuarios  LengthAwarePaginator de User (roles de esta empresa, empresas_count)
      $roles     roles disponibles con users_count (en esta empresa)
      $total     usuarios con acceso
    Filtros (GET): q, rol, estado
--}}
@extends('layouts.app')

@section('title', 'Usuarios')

@php $hayFiltros = request()->hasAny(['q', 'rol', 'estado']); @endphp

@section('content')
    <x-page-header title="Usuarios y roles" :migas="['Configuración' => null, 'Usuarios' => null]"
        subtitle="Quién entra a esta empresa y qué puede hacer. El rol se asigna por empresa: una persona puede ser Administrador aquí y Vendedor en otra.">
        <x-slot:acciones>
            <a href="{{ route('roles.index') }}" class="btn btn-secondary"><x-icon name="shield" class="text-brand-600" /> Roles y permisos</a>
            <a href="{{ route('usuarios.create') }}" class="btn btn-primary"><x-icon name="plus" /> Nuevo usuario</a>
        </x-slot:acciones>
    </x-page-header>

    <x-flash />

    {{-- Usuarios por rol --}}
    <div class="mt-6 flex flex-wrap gap-2">
        <a href="{{ route('usuarios.index') }}" @class(['rounded-full border px-3 py-1.5 text-xs font-semibold', 'border-brand-500 bg-brand-50 text-brand-700' => ! request('rol'), 'border-slate-200 bg-white text-slate-600 hover:border-brand-200' => request('rol')])>
            Todos · {{ $total }}
        </a>
        @foreach ($roles as $r)
            <a href="{{ route('usuarios.index', ['rol' => $r->id]) }}" @class(['rounded-full border px-3 py-1.5 text-xs font-semibold', 'border-brand-500 bg-brand-50 text-brand-700' => (string) request('rol') === (string) $r->id, 'border-slate-200 bg-white text-slate-600 hover:border-brand-200' => (string) request('rol') !== (string) $r->id])>
                {{ $r->name }} · {{ $r->users_count }}
            </a>
        @endforeach
    </div>

    <form method="GET" action="{{ route('usuarios.index') }}" class="mt-4 rounded-xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5">
        @if (request('rol'))<input type="hidden" name="rol" value="{{ request('rol') }}">@endif
        <div class="grid items-end gap-4 sm:grid-cols-[minmax(0,1fr)_12rem_auto]">
            <div>
                <div class="flex items-center justify-between">
                    <label for="q" class="field-label !mb-0">Buscar</label>
                    @if ($hayFiltros)<a href="{{ route('usuarios.index') }}" class="text-xs font-semibold text-brand-600 hover:text-brand-700">Limpiar filtros</a>@endif
                </div>
                <div class="relative mt-1.5">
                    <x-icon name="search" class="pointer-events-none absolute left-3 top-3 text-slate-400" />
                    <input id="q" name="q" type="search" value="{{ request('q') }}" placeholder="Nombre o correo..." class="field pl-9" autocomplete="off">
                </div>
            </div>
            <x-select name="estado" label="Estado">
                <option value="">Todos</option>
                <option value="activos" @selected(request('estado') === 'activos')>Activos</option>
                <option value="inactivos" @selected(request('estado') === 'inactivos')>Inactivos</option>
            </x-select>
            <button type="submit" class="btn btn-secondary"><x-icon name="sliders" class="text-brand-600" /> Filtrar</button>
        </div>
    </form>

    <section class="mt-6 rounded-xl border border-slate-200 bg-white shadow-sm">
        @if ($usuarios->isEmpty())
            <x-empty-state icon="users" title="Sin usuarios" description="Prueba con otros filtros o crea un usuario." />
        @else
            <ul class="divide-y divide-slate-100">
                @foreach ($usuarios as $u)
                    @php $esYo = $u->is(auth()->user()); $rol = $u->roles->first(); @endphp
                    <li @class(['flex flex-wrap items-center gap-4 p-4 sm:px-5', 'opacity-60' => ! $u->activo])>
                        <span class="grid size-10 shrink-0 place-items-center rounded-full bg-brand-50 text-sm font-semibold text-brand-700">{{ $u->iniciales }}</span>
                        <div class="min-w-0 flex-1">
                            <p class="flex flex-wrap items-center gap-2 font-semibold">
                                {{ $u->nombre_completo }}
                                @if ($esYo)<x-badge tone="info">Tú</x-badge>@endif
                                @unless ($u->activo)<x-badge tone="neutral">Inactivo</x-badge>@endunless
                            </p>
                            <p class="truncate text-xs text-slate-500">
                                {{ $u->email }}
                                @if ($u->telefono) · {{ $u->telefono }} @endif
                                @if ($u->empresas_count > 1) · trabaja en {{ $u->empresas_count }} empresas @endif
                            </p>
                        </div>
                        <div class="w-36">
                            @if ($rol)
                                <x-badge :tone="$rol->name === config('permisos.rol_administrador') ? 'info' : 'neutral'" shape="box">
                                    <x-icon name="shield" class="size-3" /> {{ $rol->name }}
                                </x-badge>
                            @else
                                <x-badge tone="warning" shape="box">Sin rol</x-badge>
                            @endif
                        </div>
                        <div class="flex items-center gap-1">
                            <a href="{{ route('usuarios.edit', $u) }}" class="btn btn-secondary !h-8 !px-2.5 text-xs"><x-icon name="pencil" class="text-brand-600" /> Editar</a>
                            @unless ($esYo)
                                <button type="button" data-eliminar="{{ route('usuarios.destroy', $u) }}" data-nombre="{{ $u->nombre_completo }}" title="Quitar acceso"
                                    aria-label="Quitar acceso a {{ $u->nombre_completo }}" class="grid size-8 place-items-center rounded-md text-red-500 hover:bg-red-50"><x-icon name="x-circle" /></button>
                            @endunless
                        </div>
                    </li>
                @endforeach
            </ul>
            @if ($usuarios->hasPages())
                <div class="border-t border-slate-200 px-4 py-3 sm:px-5">{{ $usuarios->links('vendor.pagination.ricopet') }}</div>
            @endif
        @endif
    </section>

    <x-dialogo-eliminar titulo="Quitar acceso" mensaje="Ya no podrá entrar a esta empresa. Su cuenta y su historial se conservan." />
@endsection
