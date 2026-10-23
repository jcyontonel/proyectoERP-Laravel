{{-- EmpresaController@index: $empresas (paginador con datos del ERP), $planes. Filtros: q, estado, plan_id --}}
@extends('plataforma::layouts.app')

@section('title', 'Empresas')

@php $hayFiltros = request()->hasAny(['q', 'estado', 'plan_id']); @endphp

@section('content')
    <x-plataforma::page-header title="Empresas" :migas="['Empresas' => null]" subtitle="Clientes del ERP con su plan, estado de suscripción y vencimiento.">
        <x-slot:acciones>
            <a href="{{ route('plataforma.empresas.create') }}" class="btn btn-primary"><x-plataforma::icon name="plus" /> Nueva empresa</a>
        </x-slot:acciones>
    </x-plataforma::page-header>

    <x-plataforma::flash />

    <form method="GET" class="mt-6 grid items-end gap-4 rounded-xl border border-slate-200 bg-white p-4 shadow-sm sm:grid-cols-2 sm:p-5 lg:grid-cols-[2fr_1fr_1fr_auto]">
        <div class="sm:col-span-2 lg:col-span-1">
            <div class="flex items-center justify-between">
                <label for="q" class="field-label !mb-0">Buscar</label>
                @if ($hayFiltros)<a href="{{ route('plataforma.empresas.index') }}" class="text-xs font-semibold text-brand-600">Limpiar filtros</a>@endif
            </div>
            <div class="relative mt-1.5">
                <x-plataforma::icon name="search" class="pointer-events-none absolute left-3 top-3 text-slate-400" />
                <input id="q" name="q" type="search" value="{{ request('q') }}" placeholder="RUC, razón social o nombre comercial..." class="field pl-9">
            </div>
        </div>
        <x-plataforma::select name="estado" label="Estado">
            <option value="">Todos</option>
            @foreach (config('plataforma.estados') as $valor => [, $etiqueta])
                @continue($valor === 'cancelada')
                <option value="{{ $valor }}" @selected(request('estado') === $valor)>{{ $etiqueta }}</option>
            @endforeach
            <option value="inactiva" @selected(request('estado') === 'inactiva')>Empresa desactivada</option>
        </x-plataforma::select>
        <x-plataforma::select name="plan_id" label="Plan">
            <option value="">Todos</option>
            @foreach ($planes as $p)
                <option value="{{ $p['id'] }}" @selected((string) request('plan_id') === (string) $p['id'])>{{ $p['nombre'] }}</option>
            @endforeach
        </x-plataforma::select>
        <button type="submit" class="btn btn-secondary"><x-plataforma::icon name="sliders" class="text-brand-600" /> Filtrar</button>
    </form>

    <section class="mt-6 overflow-x-auto rounded-xl border border-slate-200 bg-white shadow-sm">
        @if ($empresas->isEmpty())
            <x-plataforma::empty-state icon="building" title="Sin empresas" description="Prueba con otros filtros o registra una empresa." />
        @else
            <table class="w-full text-sm">
                <thead class="border-b border-slate-200 bg-slate-50/70 text-left text-xs font-medium text-slate-500">
                    <tr>
                        <th class="py-3 pl-5 pr-3">Empresa</th>
                        <th class="px-3 py-3">Plan</th>
                        <th class="px-3 py-3">Estado</th>
                        <th class="px-3 py-3">Pagado hasta</th>
                        <th class="px-3 py-3 text-center">Usuarios</th>
                        <th class="py-3 pl-3 pr-5"><span class="sr-only">Acciones</span></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach ($empresas as $e)
                        @php $s = $e['suscripcion']; @endphp
                        <tr @class(['hover:bg-slate-50/60', 'opacity-60' => ! $e['activo']])>
                            <td class="py-3 pl-5 pr-3">
                                <a href="{{ route('plataforma.empresas.show', $e['id']) }}" class="font-semibold hover:text-brand-600">{{ $e['nombre_comercial'] ?: $e['razon_social'] }}</a>
                                <p class="text-xs text-slate-500">RUC {{ $e['ruc'] }} @unless ($e['activo'])· desactivada @endunless</p>
                            </td>
                            <td class="px-3 py-3">
                                @if ($s)
                                    {{ $s['plan']['nombre'] }} <span class="text-xs text-slate-500">{{ config('plataforma.periodicidades')[$s['periodicidad']] ?? '' }}</span>
                                @else
                                    <span class="text-slate-400">—</span>
                                @endif
                            </td>
                            <td class="px-3 py-3"><x-plataforma::estado :valor="$s['estado_efectivo'] ?? 'sin_plan'" /></td>
                            <td class="whitespace-nowrap px-3 py-3">
                                @if ($s)
                                    {{ \Illuminate\Support\Carbon::parse($s['vence_el'])->format('d/m/Y') }}
                                    <p @class(['text-xs', 'text-red-600' => $s['dias_restantes'] < 0, 'text-amber-600' => $s['dias_restantes'] >= 0 && $s['dias_restantes'] <= 7, 'text-slate-500' => $s['dias_restantes'] > 7])>
                                        {{ $s['dias_restantes'] < 0 ? 'venció hace ' . abs($s['dias_restantes']) . ' días' : ($s['dias_restantes'] === 0 ? 'vence hoy' : 'en ' . $s['dias_restantes'] . ' días') }}
                                    </p>
                                @else
                                    —
                                @endif
                            </td>
                            <td class="px-3 py-3 text-center">{{ $e['usuarios'] ?? '—' }}</td>
                            <td class="py-3 pl-3 pr-5 text-right"><a href="{{ route('plataforma.empresas.show', $e['id']) }}" class="btn btn-secondary !h-8 !px-2.5 text-xs">Gestionar</a></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            @if ($empresas->hasPages())
                <div class="border-t border-slate-200 px-4 py-3 sm:px-5">{{ $empresas->links('vendor.pagination.ricopet') }}</div>
            @endif
        @endif
    </section>
@endsection
