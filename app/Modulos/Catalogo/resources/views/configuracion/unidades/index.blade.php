{{--
    UnidadController@index
      $unidades   TipoUnidad[]
      $usoPropio  [id => filas en esta empresa]  $usoOtras [id => bool]  $enUso [ids]
      $sunat      códigos frecuentes del catálogo 03
--}}
@extends('layouts.app')

@section('title', 'Unidades de medida')

@section('content')
    <x-page-header title="Unidades de medida" :migas="['Configuración' => null, 'Unidades de medida' => null]"
        subtitle="Unidades para vender, comprar y fraccionar, con su código SUNAT (catálogo 03) para los comprobantes electrónicos.">
        <x-slot:acciones>
            <a href="{{ route('unidades.create') }}" class="btn btn-primary"><x-icon name="plus" /> Nueva unidad</a>
        </x-slot:acciones>
    </x-page-header>

    <x-flash />

    <form method="GET" class="mt-6 flex gap-2">
        <div class="relative max-w-md flex-1">
            <x-icon name="search" class="pointer-events-none absolute left-3 top-3 text-slate-400" />
            <input name="q" type="search" value="{{ request('q') }}" placeholder="Buscar por código, descripción o código SUNAT..." class="field pl-9" aria-label="Buscar unidad">
        </div>
        <button type="submit" class="btn btn-secondary">Buscar</button>
    </form>

    <section class="mt-4 overflow-x-auto rounded-xl border border-slate-200 bg-white shadow-sm">
        <table class="w-full text-sm">
            <thead class="border-b border-slate-200 bg-slate-50/70 text-left text-xs font-medium text-slate-500">
                <tr>
                    <th class="py-3 pl-5 pr-3">Código</th>
                    <th class="px-3 py-3">Descripción</th>
                    <th class="px-3 py-3">Código SUNAT</th>
                    <th class="px-3 py-3 text-right">Uso en tu empresa</th>
                    <th class="py-3 pl-3 pr-5"><span class="sr-only">Acciones</span></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($unidades as $u)
                    @php $otras = (bool) ($usoOtras[$u->id] ?? false); @endphp
                    <tr class="hover:bg-slate-50/60">
                        <td class="py-3 pl-5 pr-3 font-mono font-semibold">{{ $u->codigo }}</td>
                        <td class="px-3 py-3">
                            {{ $u->descripcion }}
                            @if ($otras)<x-badge tone="info" class="ml-1" title="Otras empresas la usan">Compartida</x-badge>@endif
                        </td>
                        <td class="px-3 py-3">
                            @if ($u->codigo_sunat)
                                <span class="font-mono">{{ $u->codigo_sunat }}</span>
                                <span class="text-xs text-slate-500">{{ $sunat[$u->codigo_sunat] ?? '' }}</span>
                            @else
                                <x-badge tone="warning" shape="box">Sin código</x-badge>
                            @endif
                        </td>
                        <td class="px-3 py-3 text-right">{{ number_format($usoPropio[$u->id] ?? 0) }}</td>
                        <td class="py-3 pl-3 pr-5">
                            <div class="flex items-center justify-end gap-1">
                                <a href="{{ route('unidades.edit', $u) }}" title="{{ $otras ? 'Ver' : 'Editar' }}" class="grid size-8 place-items-center rounded-md text-brand-600 hover:bg-brand-50"><x-icon :name="$otras ? 'eye' : 'pencil'" /></a>
                                @unless (in_array($u->id, $enUso, true))
                                    <button type="button" data-eliminar="{{ route('unidades.destroy', $u) }}" data-nombre="{{ $u->descripcion }}" title="Eliminar" class="grid size-8 place-items-center rounded-md text-red-500 hover:bg-red-50"><x-icon name="trash" /></button>
                                @endunless
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5"><x-empty-state icon="ruler" title="Sin unidades" description="Prueba con otra búsqueda o crea una unidad." /></td></tr>
                @endforelse
            </tbody>
        </table>
    </section>

    <x-dialogo-eliminar titulo="Eliminar unidad" mensaje="Solo se eliminan unidades que ningún producto, conversión o documento usa." />
@endsection
