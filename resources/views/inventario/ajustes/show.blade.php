{{-- Ajuste de inventario: $ajuste (almacen, motivo, usuario, aprobador, detalles.producto, detalles.lote) --}}
@extends('layouts.app')

@use('App\Support\Unidades')

@section('title', 'Ajuste ' . $ajuste->numero_completo)

@section('content')
    <x-page-header :title="'Ajuste ' . $ajuste->numero_completo" :migas="['Inventario' => null, 'Ajustes' => route('ajustes.index'), $ajuste->numero_completo => null]"
        :subtitle="$ajuste->tipo_etiqueta . ' · ' . $ajuste->motivo?->nombre . ' · ' . $ajuste->almacen?->nombre . ' · ' . $ajuste->fecha_movimiento->format('d/m/Y')">
        <x-slot:badge><x-estado-inventario :estado="$ajuste->estado" /></x-slot:badge>
        <x-slot:acciones>
            @if ($ajuste->esEditable())
                <form method="POST" action="{{ route('ajustes.anular', $ajuste) }}" onsubmit="return confirm('¿Anular este ajuste?')">
                    @csrf @method('PATCH')
                    <button type="submit" class="btn btn-secondary text-red-600 hover:bg-red-50"><x-icon name="x-circle" /> Anular</button>
                </form>
                <a href="{{ route('ajustes.edit', $ajuste) }}" class="btn btn-secondary"><x-icon name="pencil" class="text-brand-600" /> Editar</a>
                @if (! $ajuste->motivo?->requiere_aprobacion || auth()->user()->can('aprobar-ajustes'))
                    <form method="POST" action="{{ route('ajustes.confirmar', $ajuste) }}" onsubmit="return confirm('Confirmar el ajuste moverá el stock. ¿Continuar?')">
                        @csrf @method('PATCH')
                        <button type="submit" class="btn btn-primary"><x-icon name="check" /> {{ $ajuste->estado === 'pendiente' ? 'Aprobar y aplicar' : 'Confirmar' }}</button>
                    </form>
                @endif
            @endif
        </x-slot:acciones>
    </x-page-header>

    <x-flash />

    @if ($ajuste->estado === 'pendiente')
        <x-alert tone="warning" class="mt-4">Este ajuste espera aprobación: el motivo «{{ $ajuste->motivo?->nombre }}» la requiere. Aún no movió el stock.</x-alert>
    @elseif ($ajuste->estado === 'borrador')
        <x-alert tone="info" class="mt-4">Es un borrador: aún no movió el stock.</x-alert>
    @endif

    <div class="mt-6 grid gap-5 lg:grid-cols-[minmax(0,1fr)_20rem]">
        <x-card title="Productos" icon="package">
            <div class="-mx-4 overflow-x-auto sm:-mx-5">
                <table class="w-full text-sm">
                    <thead class="border-y border-slate-200 bg-slate-50/70 text-left text-xs font-medium text-slate-500">
                        <tr>
                            <th class="py-2.5 pl-4 pr-3 sm:pl-5">Producto</th>
                            <th class="px-3 py-2.5">Lote</th>
                            @if ($ajuste->tipo === 'conteo')
                                <th class="px-3 py-2.5 text-right">Sistema</th>
                                <th class="px-3 py-2.5 text-right">Contado</th>
                            @endif
                            <th class="px-3 py-2.5 text-right">{{ $ajuste->tipo === 'conteo' ? 'Diferencia' : 'Cantidad' }}</th>
                            <th class="py-2.5 pl-3 pr-4 text-right sm:pr-5">Valor</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($ajuste->detalles as $d)
                            @php $mov = $ajuste->tipo === 'egreso' ? -$d->cantidad : $d->cantidad; @endphp
                            <tr>
                                <td class="py-2.5 pl-4 pr-3 sm:pl-5">
                                    <a href="{{ route('productos.show', $d->producto_id) }}" class="font-medium hover:text-brand-600">{{ $d->producto?->nombre }}</a>
                                    <p class="text-xs text-slate-500">{{ $d->producto?->tipoUnidad?->descripcion }}</p>
                                </td>
                                <td class="px-3 py-2.5 text-slate-600">{{ $d->lote?->codigo_lote ?? '—' }}</td>
                                @if ($ajuste->tipo === 'conteo')
                                    <td class="px-3 py-2.5 text-right">{{ Unidades::cantidad($d->stock_sistema) }}</td>
                                    <td class="px-3 py-2.5 text-right">{{ Unidades::cantidad($d->stock_contado) }}</td>
                                @endif
                                <td @class(['px-3 py-2.5 text-right font-semibold', 'text-emerald-700' => $mov > 0, 'text-red-600' => $mov < 0])>{{ $mov > 0 ? '+' : '' }}{{ Unidades::cantidad($mov) }}</td>
                                <td class="py-2.5 pl-3 pr-4 text-right sm:pr-5"><x-money :value="$mov * $d->costo_unitario" /></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </x-card>

        <aside>
            <x-card title="Datos" icon="info">
                <dl class="divide-y divide-slate-100 text-sm">
                    <x-dato label="Tipo">{{ $ajuste->tipo_etiqueta }}</x-dato>
                    <x-dato label="Motivo">{{ $ajuste->motivo?->nombre }}</x-dato>
                    <x-dato label="Almacén">{{ $ajuste->almacen?->nombre }}</x-dato>
                    <x-dato label="Registrado por">{{ $ajuste->usuario?->nombre_completo }}</x-dato>
                    @if ($ajuste->aprobador)<x-dato label="Aprobado por">{{ $ajuste->aprobador->nombre_completo }}</x-dato>@endif
                    @if ($ajuste->confirmado_at)<x-dato label="Confirmado">{{ $ajuste->confirmado_at->format('d/m/Y H:i') }}</x-dato>@endif
                    <x-dato label="Valor total">
                        <x-money :value="$ajuste->detalles->sum(fn ($d) => ($ajuste->tipo === 'egreso' ? -$d->cantidad : $d->cantidad) * $d->costo_unitario)" />
                    </x-dato>
                </dl>
                @if ($ajuste->observacion)
                    <p class="mt-3 whitespace-pre-line border-t border-slate-100 pt-3 text-sm text-slate-600">{{ $ajuste->observacion }}</p>
                @endif
            </x-card>
        </aside>
    </div>
@endsection
