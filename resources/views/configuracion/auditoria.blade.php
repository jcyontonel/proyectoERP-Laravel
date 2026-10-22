{{--
    AuditoriaController@index
      $eventos   LengthAwarePaginator de AuditoriaEvento (usuario)
      $usuarios  usuarios con eventos en la empresa
      $entidades [clase => etiqueta]  $acciones [accion => etiqueta]  $hoy eventos de hoy
    Filtros (GET): usuario, entidad, accion, id, desde, hasta
--}}
@extends('layouts.app')

@section('title', 'Auditoría')

@php
    $hayFiltros = request()->hasAny(['usuario', 'entidad', 'accion', 'id', 'desde', 'hasta']);
    $tonos = ['creado' => 'success', 'actualizado' => 'info', 'eliminado' => 'danger', 'acceso fallido' => 'danger', 'acceso quitado' => 'danger', 'permisos' => 'warning', 'rol asignado' => 'warning'];
    $mostrar = fn ($v) => is_bool($v) ? ($v ? 'Sí' : 'No') : (is_array($v) ? implode(', ', array_map(fn ($x) => is_scalar($x) ? $x : json_encode($x, JSON_UNESCAPED_UNICODE), $v)) : ($v ?? '—'));
@endphp

@section('content')
    <x-page-header title="Auditoría" :migas="['Configuración' => null, 'Auditoría' => null]"
        subtitle="Quién hizo qué y cuándo en esta empresa: altas, cambios (con el valor anterior y el nuevo), anulaciones, permisos y accesos.">
        <x-slot:acciones>
            <a href="{{ route('config.auditoria.export', request()->except('page')) }}" class="btn btn-secondary"><x-icon name="download" class="text-brand-600" /> Exportar CSV</a>
        </x-slot:acciones>
    </x-page-header>

    <x-flash />

    <form method="GET" action="{{ route('config.auditoria') }}" class="mt-6 rounded-xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5">
        <div class="grid grid-cols-2 items-end gap-4 lg:grid-cols-[1fr_1fr_1fr_7rem_1.5fr_auto]">
            <x-select name="usuario" label="Usuario">
                <option value="">Todos</option>
                @foreach ($usuarios as $u)
                    <option value="{{ $u->id }}" @selected((string) request('usuario') === (string) $u->id)>{{ $u->nombre_completo }}</option>
                @endforeach
            </x-select>
            <x-select name="entidad" label="Registro">
                <option value="">Todos</option>
                @foreach ($entidades as $valor => $etiqueta)
                    <option value="{{ $valor }}" @selected(request('entidad') === $valor)>{{ $etiqueta }}</option>
                @endforeach
            </x-select>
            <x-select name="accion" label="Acción">
                <option value="">Todas</option>
                @foreach ($acciones as $valor => $etiqueta)
                    <option value="{{ $valor }}" @selected(request('accion') === $valor)>{{ $etiqueta }}</option>
                @endforeach
            </x-select>
            <x-input name="id" label="ID" type="number" min="1" :value="request('id')" />
            <div class="col-span-2 lg:col-span-1">
                <span class="field-label">Fechas</span>
                <div class="field flex items-center gap-2 px-3 focus-within:border-brand-500 focus-within:ring-2 focus-within:ring-brand-500/20">
                    <x-icon name="calendar" class="text-slate-400" />
                    <input type="date" name="desde" value="{{ request('desde') }}" aria-label="Desde" class="min-w-0 flex-1 border-0 bg-transparent p-0 text-sm focus:outline-none">
                    <span class="text-slate-400" aria-hidden="true">–</span>
                    <input type="date" name="hasta" value="{{ request('hasta') }}" aria-label="Hasta" class="min-w-0 flex-1 border-0 bg-transparent p-0 text-sm focus:outline-none">
                </div>
            </div>
            <div class="flex gap-2">
                <button type="submit" class="btn btn-secondary"><x-icon name="sliders" class="text-brand-600" /> Filtrar</button>
                @if ($hayFiltros)<a href="{{ route('config.auditoria') }}" class="btn btn-secondary" title="Limpiar"><x-icon name="x" /></a>@endif
            </div>
        </div>
    </form>

    <section class="mt-6 rounded-xl border border-slate-200 bg-white shadow-sm">
        <header class="p-4 sm:px-5">
            <h2 class="text-base font-semibold">Eventos</h2>
            <p class="mt-0.5 text-xs text-slate-500">{{ number_format($eventos->total()) }} eventos · {{ $hoy }} hoy · Del más reciente al más antiguo</p>
        </header>

        @if ($eventos->isEmpty())
            <x-empty-state class="border-t border-slate-200" icon="eye" title="Sin eventos" description="Aún no hay actividad registrada con estos filtros." />
        @else
            <ul class="divide-y divide-slate-100 border-t border-slate-200">
                @foreach ($eventos as $e)
                    @php $cambios = array_unique(array_merge(array_keys($e->datos_anteriores ?? []), array_keys($e->datos_nuevos ?? []))); @endphp
                    <li>
                        <details class="group">
                            <summary @class(['flex cursor-pointer list-none flex-wrap items-center gap-x-4 gap-y-1 px-4 py-3 hover:bg-slate-50/60 sm:px-5 [&::-webkit-details-marker]:hidden', 'cursor-default' => ! $cambios])>
                                <span class="w-32 shrink-0 text-xs text-slate-500">{{ $e->created_at->format('d/m/Y H:i:s') }}</span>
                                <span class="min-w-40 flex-1 text-sm">
                                    <strong>{{ $e->usuario?->nombre_completo ?? 'Sistema' }}</strong>
                                    <x-badge :tone="$tonos[$e->accion] ?? 'neutral'" shape="box" class="mx-1">{{ $acciones[$e->accion] ?? $e->accion }}</x-badge>
                                    {{ $entidades[$e->entidad] ?? $e->entidad }}@if ($e->entidad_id) <span class="font-mono text-xs text-slate-500">#{{ $e->entidad_id }}</span>@endif
                                </span>
                                <span class="text-xs text-slate-400">{{ $e->ip }}</span>
                                @if ($cambios)
                                    <x-icon name="chevron-down" class="size-4 text-slate-400 transition group-open:rotate-180" />
                                @endif
                            </summary>
                            @if ($cambios)
                                <div class="overflow-x-auto px-4 pb-4 sm:px-5">
                                    <table class="w-full text-xs">
                                        <thead class="text-left text-slate-500">
                                            <tr><th class="py-1.5 pr-3">Campo</th><th class="px-3 py-1.5">Antes</th><th class="px-3 py-1.5">Después</th></tr>
                                        </thead>
                                        <tbody class="divide-y divide-slate-100">
                                            @foreach ($cambios as $campo)
                                                <tr>
                                                    <td class="py-1.5 pr-3 font-mono text-slate-600">{{ $campo }}</td>
                                                    <td class="px-3 py-1.5 text-red-700">{{ array_key_exists($campo, $e->datos_anteriores ?? []) ? $mostrar($e->datos_anteriores[$campo]) : '' }}</td>
                                                    <td class="px-3 py-1.5 text-emerald-700">{{ array_key_exists($campo, $e->datos_nuevos ?? []) ? $mostrar($e->datos_nuevos[$campo]) : '' }}</td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            @endif
                        </details>
                    </li>
                @endforeach
            </ul>
            <div class="border-t border-slate-200 px-4 py-3 sm:px-5">{{ $eventos->links('vendor.pagination.ricopet') }}</div>
        @endif
    </section>
@endsection
