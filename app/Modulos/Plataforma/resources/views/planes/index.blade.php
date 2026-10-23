{{-- PlanController@index: $planes (con precios y empresas con el plan vigente) --}}
@extends('plataforma::layouts.app')

@section('title', 'Planes')

@section('content')
    <x-plataforma::page-header title="Planes" :migas="['Planes' => null]" subtitle="Límites y precios de cada plan. Los cambios de límites aplican de inmediato a las empresas que lo tienen; los de precio, desde el próximo cobro.">
        <x-slot:acciones><a href="{{ route('plataforma.planes.create') }}" class="btn btn-primary"><x-plataforma::icon name="plus" /> Nuevo plan</a></x-slot:acciones>
    </x-plataforma::page-header>

    <x-plataforma::flash />

    <section class="mt-6 grid gap-4 md:grid-cols-2 xl:grid-cols-3">
        @foreach ($planes as $p)
            <article @class(['flex flex-col rounded-xl border bg-white p-5 shadow-sm', 'border-slate-200' => $p['activo'], 'border-dashed border-slate-300 opacity-70' => ! $p['activo']])>
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <h2 class="text-lg font-bold">{{ $p['nombre'] }}</h2>
                        <p class="font-mono text-xs text-slate-500">{{ $p['codigo'] }}</p>
                    </div>
                    <x-plataforma::badge :tone="$p['activo'] ? 'success' : 'neutral'" dot>{{ $p['activo'] ? 'Activo' : 'Desactivado' }}</x-plataforma::badge>
                </div>
                @if ($p['descripcion'])<p class="mt-2 text-sm text-slate-600">{{ $p['descripcion'] }}</p>@endif

                <dl class="mt-4 grid grid-cols-2 gap-2 text-sm">
                    @foreach (['max_comprobantes_mes' => 'Comprobantes/mes', 'max_productos' => 'Productos', 'max_usuarios' => 'Usuarios', 'max_almacenes' => 'Almacenes'] as $campo => $etiqueta)
                        <div class="rounded-lg bg-slate-50 px-3 py-2"><dt class="text-[11px] text-slate-500">{{ $etiqueta }}</dt><dd class="font-semibold">{{ $p[$campo] !== null ? number_format($p[$campo]) : 'Ilimitado' }}</dd></div>
                    @endforeach
                </dl>

                <ul class="mt-4 space-y-1 text-sm">
                    @forelse (collect($p['precios'])->where('activo', true) as $pr)
                        <li class="flex justify-between"><span class="text-slate-600">{{ config('plataforma.periodicidades')[$pr['periodicidad']] ?? $pr['periodicidad'] }}</span><strong><x-plataforma::money :value="$pr['precio']" /></strong></li>
                    @empty
                        <li class="text-slate-500">Sin precios activos.</li>
                    @endforelse
                </ul>

                @php $nombresModulos = collect($catalogoModulos)->pluck('nombre', 'clave'); @endphp
                <div class="mt-4 border-t border-slate-100 pt-3">
                    <p class="text-[11px] font-medium text-slate-500">{{ $p['producto_nombre'] ?? '' }} · {{ count($p['funcionalidades'] ?? []) }} funcionalidades</p>
                    <p class="mt-1 text-xs text-slate-600">{{ collect($p['funcionalidades'] ?? [])->map(fn ($m) => $nombresModulos[$m] ?? $m)->implode(' · ') }}</p>
                </div>

                <div class="min-h-4 flex-1"></div>
                <div class="mt-4 flex items-center justify-between border-t border-slate-100 pt-3 text-xs">
                    <span class="text-slate-500">{{ $p['empresas'] ?? 0 }} {{ ($p['empresas'] ?? 0) === 1 ? 'empresa' : 'empresas' }} · {{ $p['dias_prueba'] }} días de prueba</span>
                    <span class="flex gap-3 font-semibold">
                        <a href="{{ route('plataforma.planes.edit', $p['id']) }}" class="text-brand-600 hover:text-brand-700">Editar</a>
                        @if (($p['empresas'] ?? 0) === 0)
                            <form method="POST" action="{{ route('plataforma.planes.destroy', $p['id']) }}" data-confirmar="¿Eliminar el plan {{ $p['nombre'] }}? Si alguna empresa lo tuvo, el ERP no lo permitirá: desactívalo.">
                                @csrf @method('DELETE')
                                <button type="submit" class="text-red-500 hover:text-red-600">Eliminar</button>
                            </form>
                        @endif
                    </span>
                </div>
            </article>
        @endforeach
    </section>
@endsection
