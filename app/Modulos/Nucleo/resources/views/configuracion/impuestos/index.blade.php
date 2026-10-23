{{--
    ImpuestoController@index
      $impuestos     Impuesto[]
      $usoPropio     [impuesto_id => filas en esta empresa]
      $usoOtras      [impuesto_id => bool: lo usa otra empresa]
      $afectaciones  Impuesto::AFECTACIONES
--}}
@extends('layouts.app')

@section('title', 'Impuestos')

@section('content')
    <x-page-header title="Impuestos" :migas="['Configuración' => null, 'Impuestos' => null]"
        subtitle="Afectaciones al IGV según el catálogo SUNAT 07. Cada producto usa una; si no tiene, se aplica el IGV de la empresa.">
        <x-slot:acciones>
            <a href="{{ route('impuestos.create') }}" class="btn btn-primary"><x-icon name="plus" /> Agregar afectación</a>
        </x-slot:acciones>
    </x-page-header>

    <x-flash />

    <x-alert tone="info" class="mt-6">Este catálogo es común a todas las empresas del sistema: solo puedes modificar las afectaciones que ninguna otra empresa usa.</x-alert>

    <section class="mt-6 overflow-x-auto rounded-xl border border-slate-200 bg-white shadow-sm">
        <table class="w-full text-sm">
            <thead class="border-b border-slate-200 bg-slate-50/70 text-left text-xs font-medium text-slate-500">
                <tr>
                    <th class="py-3 pl-5 pr-3">Código</th>
                    <th class="px-3 py-3">Afectación</th>
                    <th class="px-3 py-3">SUNAT (07 / 05)</th>
                    <th class="px-3 py-3 text-right">Tasa</th>
                    <th class="px-3 py-3 text-right">Uso en tu empresa</th>
                    <th class="px-3 py-3">Estado</th>
                    <th class="py-3 pl-3 pr-5"><span class="sr-only">Acciones</span></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($impuestos as $i)
                    @php $otras = (bool) ($usoOtras[$i->id] ?? false); $propio = (int) ($usoPropio[$i->id] ?? 0); @endphp
                    <tr @class(['hover:bg-slate-50/60', 'text-slate-400' => ! $i->activo])>
                        <td class="py-3 pl-5 pr-3 font-mono font-semibold">{{ $i->codigo }}</td>
                        <td class="px-3 py-3">
                            <p class="font-medium">{{ $i->nombre }}</p>
                            <p class="text-xs text-slate-500">{{ $afectaciones[$i->tipo_afectacion][0] ?? '—' }}</p>
                        </td>
                        <td class="px-3 py-3 font-mono text-xs">{{ $i->tipo_afectacion }} / {{ $i->codigo_tributo }}</td>
                        <td class="px-3 py-3 text-right">{{ rtrim(rtrim(number_format($i->porcentaje, 2), '0'), '.') }} %</td>
                        <td class="px-3 py-3 text-right">{{ number_format($propio) }}</td>
                        <td class="px-3 py-3">
                            @if ($i->activo)<x-badge tone="success" dot>Activa</x-badge>@else<x-badge tone="neutral" dot>Inactiva</x-badge>@endif
                            @if ($otras)<x-badge tone="info" class="ml-1" title="Otras empresas la usan">Compartida</x-badge>@endif
                        </td>
                        <td class="py-3 pl-3 pr-5">
                            <div class="flex items-center justify-end gap-1">
                                @if ($otras)
                                    <a href="{{ route('impuestos.edit', $i) }}" title="Ver" class="grid size-8 place-items-center rounded-md text-slate-500 hover:bg-slate-100"><x-icon name="eye" /></a>
                                @else
                                    <a href="{{ route('impuestos.edit', $i) }}" title="Editar" class="grid size-8 place-items-center rounded-md text-brand-600 hover:bg-brand-50"><x-icon name="pencil" /></a>
                                @endif
                                @if (! $otras && $propio === 0)
                                    <button type="button" data-eliminar="{{ route('impuestos.destroy', $i) }}" data-nombre="{{ $i->nombre }}" title="Eliminar" class="grid size-8 place-items-center rounded-md text-red-500 hover:bg-red-50"><x-icon name="trash" /></button>
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7"><x-empty-state icon="percent" title="Sin afectaciones" description="Agrega al menos la afectación Gravado - IGV." /></td></tr>
                @endforelse
            </tbody>
        </table>
    </section>

    <x-dialogo-eliminar titulo="Eliminar afectación" mensaje="Solo se eliminan afectaciones que ningún producto ni documento usa." />
@endsection
