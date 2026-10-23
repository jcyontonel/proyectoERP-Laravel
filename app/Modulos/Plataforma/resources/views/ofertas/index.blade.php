{{-- OfertaController@index: $ofertas --}}
@extends('plataforma::layouts.app')

@section('title', 'Ofertas')

@section('content')
    <x-plataforma::page-header title="Ofertas" :migas="['Ofertas' => null]" subtitle="Descuentos al asignar un plan: porcentaje o monto fijo, por plan y periodicidad, con vigencia, cupón y máximo de usos.">
        <x-slot:acciones><a href="{{ route('plataforma.ofertas.create') }}" class="btn btn-primary"><x-plataforma::icon name="plus" /> Nueva oferta</a></x-slot:acciones>
    </x-plataforma::page-header>

    <x-plataforma::flash />

    <section class="mt-6 overflow-x-auto rounded-xl border border-slate-200 bg-white shadow-sm">
        @if (empty($ofertas))
            <x-plataforma::empty-state icon="tag" title="Sin ofertas" description="Crea una para lanzamientos, campañas o clientes especiales." />
        @else
            <table class="w-full text-sm">
                <thead class="border-b border-slate-200 bg-slate-50/70 text-left text-xs font-medium text-slate-500">
                    <tr>
                        <th class="py-3 pl-5 pr-3">Oferta</th><th class="px-3 py-3">Descuento</th><th class="px-3 py-3">Aplica a</th>
                        <th class="px-3 py-3">Vigencia</th><th class="px-3 py-3 text-center">Usos</th><th class="px-3 py-3">Estado</th><th class="py-3 pl-3 pr-5"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach ($ofertas as $o)
                        <tr class="hover:bg-slate-50/60">
                            <td class="py-3 pl-5 pr-3">
                                <p class="font-medium">{{ $o['nombre'] }}</p>
                                @if ($o['codigo'])<p class="font-mono text-xs text-brand-700">{{ $o['codigo'] }}</p>@endif
                            </td>
                            <td class="px-3 py-3 font-semibold">
                                {{ $o['tipo'] === 'porcentaje' ? rtrim(rtrim(number_format($o['valor'], 2), '0'), '.') . ' %' : 'S/ ' . number_format($o['valor'], 2) }}
                                <p class="text-xs font-normal text-slate-500">{{ $o['solo_primer_pago'] ? 'Solo el primer pago' : 'Todos los pagos' }}</p>
                            </td>
                            <td class="px-3 py-3">{{ $o['plan'] ?? 'Todos los planes' }} <span class="text-xs text-slate-500">· {{ $o['periodicidad'] ? config('plataforma.periodicidades')[$o['periodicidad']] : 'cualquier periodicidad' }}</span></td>
                            <td class="whitespace-nowrap px-3 py-3 text-xs">{{ date('d/m/Y', strtotime($o['desde'])) }} – {{ $o['hasta'] ? date('d/m/Y', strtotime($o['hasta'])) : 'sin fin' }}</td>
                            <td class="px-3 py-3 text-center">{{ $o['usos'] }}{{ $o['max_usos'] ? ' / ' . $o['max_usos'] : '' }}</td>
                            <td class="px-3 py-3"><x-plataforma::badge :tone="$o['vigente'] ? 'success' : 'neutral'" dot>{{ $o['vigente'] ? 'Vigente' : ($o['activo'] ? 'Fuera de vigencia' : 'Desactivada') }}</x-plataforma::badge></td>
                            <td class="py-3 pl-3 pr-5 text-right">
                                <span class="flex justify-end gap-3 text-xs font-semibold">
                                    <a href="{{ route('plataforma.ofertas.edit', $o['id']) }}" class="text-brand-600">Editar</a>
                                    @if ($o['usos'] === 0)
                                        <form method="POST" action="{{ route('plataforma.ofertas.destroy', $o['id']) }}" data-confirmar="¿Eliminar la oferta {{ $o['nombre'] }}?">
                                            @csrf @method('DELETE')
                                            <button type="submit" class="text-red-500">Eliminar</button>
                                        </form>
                                    @endif
                                </span>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </section>
@endsection
