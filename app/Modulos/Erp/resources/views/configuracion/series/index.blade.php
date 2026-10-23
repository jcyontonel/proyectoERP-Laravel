{{-- SerieController@index: $series [modulo => Correlativo[]], $modulos [modulo => etiqueta], $tipos (Correlativo::TIPOS) --}}
@extends('layouts.app')

@section('title', 'Series y correlativos')

@section('content')
    <x-page-header title="Series y correlativos" :migas="['Configuración' => null, 'Series y correlativos' => null]"
        subtitle="Cada serie lleva su propio correlativo. Una serie que ya emitió documentos no cambia de nombre: puedes desactivarla y crear otra.">
        <x-slot:acciones>
            <a href="{{ route('series.create') }}" class="btn btn-primary"><x-icon name="plus" /> Nueva serie</a>
        </x-slot:acciones>
    </x-page-header>

    <x-flash />

    <div class="mt-6 space-y-6">
        @foreach ($modulos as $modulo => $etiqueta)
            @php $lista = $series[$modulo] ?? collect(); @endphp
            <section class="rounded-xl border border-slate-200 bg-white shadow-sm" aria-labelledby="mod-{{ $modulo }}">
                <header class="flex items-center justify-between gap-3 p-4 sm:px-5">
                    <div>
                        <h2 id="mod-{{ $modulo }}" class="text-base font-semibold">{{ $etiqueta }}</h2>
                        <p class="text-xs text-slate-500">{{ collect($tipos[$modulo])->map(fn ($t) => $t[0] . ' (' . $t[2] . ')')->join(' · ') }}</p>
                    </div>
                    <a href="{{ route('series.create', ['modulo' => $modulo]) }}" class="btn btn-secondary !h-8 !px-2.5 text-xs"><x-icon name="plus" class="text-brand-600" /> Agregar</a>
                </header>

                @if ($lista->isEmpty())
                    <p class="border-t border-slate-100 px-5 py-4 text-xs text-slate-500">
                        Sin series. {{ $modulo === 'venta' ? 'Crea al menos una para poder vender.' : 'Se creará la serie por defecto al registrar el primer documento.' }}
                    </p>
                @else
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead class="border-y border-slate-200 bg-slate-50/70 text-left text-xs font-medium text-slate-500">
                                <tr>
                                    <th class="py-2.5 pl-5 pr-3">Serie</th>
                                    <th class="px-3 py-2.5">Comprobante</th>
                                    <th class="px-3 py-2.5 text-right">Último emitido</th>
                                    <th class="px-3 py-2.5">Próximo</th>
                                    <th class="px-3 py-2.5">Estado</th>
                                    <th class="py-2.5 pl-3 pr-5 text-right"><span class="sr-only">Acciones</span></th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                @foreach ($lista as $s)
                                    <tr @class(['hover:bg-slate-50/60', 'text-slate-400' => ! $s->activo])>
                                        <td class="py-3 pl-5 pr-3 font-mono font-semibold">{{ $s->serie }}</td>
                                        <td class="px-3 py-3">{{ $s->destipo }} <span class="text-xs text-slate-400">({{ $s->tipo }})</span></td>
                                        <td class="px-3 py-3 text-right font-mono">{{ $s->sinUso() ? '—' : \App\Modulos\Erp\Models\Correlativo::formatear($s->numero) }}</td>
                                        <td class="px-3 py-3 font-mono text-slate-600">{{ $s->serie }}-{{ $s->siguiente() }}</td>
                                        <td class="px-3 py-3">
                                            @if ($s->activo)<x-badge tone="success" dot>Activa</x-badge>@else<x-badge tone="neutral" dot>Inactiva</x-badge>@endif
                                            @if ($s->sinUso())<x-badge tone="info" class="ml-1">Sin uso</x-badge>@endif
                                        </td>
                                        <td class="py-3 pl-3 pr-5">
                                            <div class="flex items-center justify-end gap-1">
                                                <a href="{{ route('series.edit', $s) }}" title="Editar" aria-label="Editar serie {{ $s->serie }}" class="grid size-8 place-items-center rounded-md text-brand-600 hover:bg-brand-50"><x-icon name="pencil" /></a>
                                                @if ($s->sinUso())
                                                    <button type="button" data-eliminar="{{ route('series.destroy', $s) }}" data-nombre="la serie {{ $s->serie }}" title="Eliminar" aria-label="Eliminar serie {{ $s->serie }}" class="grid size-8 place-items-center rounded-md text-red-500 hover:bg-red-50"><x-icon name="trash" /></button>
                                                @endif
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </section>
        @endforeach
    </div>

    <x-dialogo-eliminar titulo="Eliminar serie" mensaje="Solo se eliminan series que no han emitido documentos." />
@endsection
