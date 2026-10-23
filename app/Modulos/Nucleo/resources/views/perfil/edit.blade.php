{{-- PerfilController@edit: $user, $empresas (con acceso), $roles [empresa_id => rol] --}}
@extends('layouts.app')

@section('title', 'Mi perfil')

@section('content')
    <x-page-header title="Mi perfil" :migas="['Mi cuenta' => null, 'Mi perfil' => null]" subtitle="Tus datos de acceso y las empresas en las que trabajas.">
        <x-slot:acciones>
            <a href="{{ route('perfil.preferencias') }}" class="btn btn-secondary"><x-icon name="sliders" class="text-brand-600" /> Preferencias</a>
        </x-slot:acciones>
    </x-page-header>

    <x-flash />

    <div class="mt-6 grid max-w-5xl gap-5 lg:grid-cols-[minmax(0,1fr)_20rem]">
        <div class="space-y-5">
            <form method="POST" action="{{ route('perfil.update') }}">
                @csrf
                @method('PUT')
                <x-card title="Datos personales" icon="user">
                    <div class="grid gap-4 sm:grid-cols-2">
                        <x-input name="name" label="Nombres *" :value="$user->name" required maxlength="100" />
                        <x-input name="apellido" label="Apellidos" :value="$user->apellido" maxlength="100" />
                        <x-input name="email" label="Correo (para iniciar sesión) *" type="email" :value="$user->email" required maxlength="255" />
                        <x-input name="telefono" label="Teléfono" type="tel" :value="$user->telefono" maxlength="30" />
                    </div>
                    <div class="mt-5 flex justify-end border-t border-slate-100 pt-4">
                        <button type="submit" class="btn btn-primary"><x-icon name="save" /> Guardar datos</button>
                    </div>
                </x-card>
            </form>

            <form method="POST" action="{{ route('perfil.password') }}">
                @csrf
                @method('PUT')
                <x-card title="Cambiar contraseña" icon="lock" subtitle="Mínimo 8 caracteres con letras y números. Se cerrarán tus sesiones en otros equipos.">
                    <div class="grid gap-4 sm:grid-cols-3">
                        <x-input name="password_actual" label="Contraseña actual" type="password" autocomplete="current-password" value="" required />
                        <x-input name="password" label="Nueva contraseña" type="password" autocomplete="new-password" value="" required />
                        <x-input name="password_confirmation" label="Repetir nueva" type="password" autocomplete="new-password" value="" required />
                    </div>
                    <div class="mt-5 flex justify-end border-t border-slate-100 pt-4">
                        <button type="submit" class="btn btn-primary"><x-icon name="lock" /> Cambiar contraseña</button>
                    </div>
                </x-card>
            </form>
        </div>

        <aside class="space-y-5">
            <x-card title="Mi cuenta" icon="shield">
                <div class="flex items-center gap-3">
                    <span class="grid size-12 place-items-center rounded-full bg-brand-50 text-base font-semibold text-brand-700">{{ $user->iniciales }}</span>
                    <div class="min-w-0">
                        <p class="truncate font-semibold">{{ $user->nombre_completo }}</p>
                        <p class="truncate text-xs text-slate-500">Desde {{ $user->created_at?->translatedFormat('d M Y') ?? '—' }}</p>
                    </div>
                </div>
            </x-card>
            <x-card title="Mis empresas" icon="store" subtitle="Tu rol puede ser distinto en cada empresa.">
                <ul class="space-y-2 text-sm">
                    @foreach ($empresas as $e)
                        <li class="flex items-center justify-between gap-2">
                            <span class="min-w-0 truncate">{{ $e->nombre_comercial ?: $e->razon_social }}</span>
                            <x-badge tone="neutral" shape="box">{{ $roles[$e->id] ?? 'Sin rol' }}</x-badge>
                        </li>
                    @endforeach
                </ul>
            </x-card>
        </aside>
    </div>
@endsection
