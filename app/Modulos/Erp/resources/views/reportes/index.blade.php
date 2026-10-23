{{-- ReporteController@index: $grupos [grupo => [clave, titulo, descripcion, icono]] --}}
@extends('layouts.app')

@section('title', 'Reportes')

@section('content')
    <x-page-header title="Reportes" :migas="['Reportes' => null]" subtitle="Información para decidir: ventas, rentabilidad, inventario, compras, caja y contabilidad. Todos se exportan a Excel (CSV)." />

    @foreach ($grupos as $grupo => $reportes)
        <h2 class="mt-8 text-xs font-semibold uppercase tracking-wide text-slate-400">{{ $grupo }}</h2>
        <div class="mt-3 grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
            @foreach ($reportes as $r)
                <a href="{{ route('reportes.' . $r['clave']) }}" class="group flex gap-4 rounded-xl border border-slate-200 bg-white p-5 shadow-sm transition hover:border-brand-200 hover:shadow">
                    <span class="grid size-11 shrink-0 place-items-center rounded-lg bg-brand-50 text-brand-600"><x-icon :name="$r['icono']" class="size-5" /></span>
                    <span class="min-w-0">
                        <span class="block font-semibold group-hover:text-brand-700">{{ $r['titulo'] }}</span>
                        <span class="mt-1 block text-xs leading-relaxed text-slate-500">{{ $r['descripcion'] }}</span>
                    </span>
                </a>
            @endforeach
        </div>
    @endforeach
@endsection
