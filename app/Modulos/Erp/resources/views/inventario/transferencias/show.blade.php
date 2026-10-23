{{-- Transferencia: $transferencia (origen, destino, usuario, receptor, detalles.producto, detalles.lote) --}}
@extends('layouts.app')

@use('App\Modulos\Catalogo\Support\Unidades')

@php
    $t = $transferencia;
    $enTransito = $t->estado === \App\Modulos\Erp\Models\TransferenciaAlmacen::EN_TRANSITO;
@endphp

@section('title', 'Transferencia ' . $t->numero_completo)

@section('content')
    <x-page-header :title="'Transferencia ' . $t->numero_completo" :migas="['Inventario' => null, 'Transferencias' => route('transferencias.index'), $t->numero_completo => null]"
        :subtitle="$t->origen?->nombre . ' → ' . $t->destino?->nombre . ' · ' . $t->fecha_movimiento->format('d/m/Y')">
        <x-slot:badge><x-estado-inventario :estado="$t->estado" /></x-slot:badge>
        <x-slot:acciones>
            @if (in_array($t->estado, ['borrador', 'en_transito'], true))
                <form method="POST" action="{{ route('transferencias.anular', $t) }}" onsubmit="return confirm('{{ $enTransito ? '¿Anular el envío? La mercadería volverá al almacén de origen.' : '¿Anular esta transferencia?' }}')">
                    @csrf @method('PATCH')
                    <button type="submit" class="btn btn-secondary text-red-600 hover:bg-red-50"><x-icon name="x-circle" /> Anular</button>
                </form>
            @endif
            @if ($t->estado === 'borrador')
                <a href="{{ route('transferencias.edit', $t) }}" class="btn btn-secondary"><x-icon name="pencil" class="text-brand-600" /> Editar</a>
                <form method="POST" action="{{ route('transferencias.enviar', $t) }}">
                    @csrf @method('PATCH')
                    <button type="submit" class="btn btn-primary"><x-icon name="truck" /> Enviar</button>
                </form>
            @endif
        </x-slot:acciones>
    </x-page-header>

    <x-flash />

    <form method="POST" action="{{ route('transferencias.recibir', $t) }}" class="mt-6 grid gap-5 lg:grid-cols-[minmax(0,1fr)_20rem]">
        @csrf @method('PATCH')

        <x-card title="Productos" icon="package" :subtitle="$enTransito ? 'Revisa lo que llegó: si falta algo, corrige la cantidad recibida.' : null">
            <div class="-mx-4 overflow-x-auto sm:-mx-5">
                <table class="w-full text-sm">
                    <thead class="border-y border-slate-200 bg-slate-50/70 text-left text-xs font-medium text-slate-500">
                        <tr>
                            <th class="py-2.5 pl-4 pr-3 sm:pl-5">Producto</th>
                            <th class="px-3 py-2.5">Lote</th>
                            <th class="px-3 py-2.5 text-right">Solicitado</th>
                            <th class="px-3 py-2.5 text-right">Enviado</th>
                            <th class="py-2.5 pl-3 pr-4 text-right sm:pr-5">Recibido</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($t->detalles as $d)
                            <tr>
                                <td class="py-2.5 pl-4 pr-3 sm:pl-5">
                                    <a href="{{ route('productos.show', $d->producto_id) }}" class="font-medium hover:text-brand-600">{{ $d->producto?->nombre }}</a>
                                    <p class="text-xs text-slate-500">{{ $d->producto?->tipoUnidad?->descripcion }}</p>
                                </td>
                                <td class="px-3 py-2.5 text-slate-600">{{ $d->lote?->codigo_lote ?? '—' }}</td>
                                <td class="px-3 py-2.5 text-right">{{ Unidades::cantidad($d->cantidad_solicitada) }}</td>
                                <td class="px-3 py-2.5 text-right">{{ $t->estado === 'borrador' ? '—' : Unidades::cantidad($d->cantidad_enviada) }}</td>
                                <td class="py-2.5 pl-3 pr-4 text-right sm:pr-5">
                                    @if ($enTransito)
                                        <input type="number" name="recibidas[{{ $d->id }}]" value="{{ old('recibidas.' . $d->id, (float) $d->cantidad_enviada) }}" step="0.0001" min="0" max="{{ (float) $d->cantidad_enviada }}"
                                            aria-label="Recibido de {{ $d->producto?->nombre }}" class="field ml-auto h-8 w-28 text-right">
                                    @elseif ($t->estado === 'recibida')
                                        <span @class(['font-semibold', 'text-amber-700' => $d->cantidad_recibida < $d->cantidad_enviada])>{{ Unidades::cantidad($d->cantidad_recibida) }}</span>
                                    @else
                                        —
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </x-card>

        <aside class="space-y-5">
            <x-card title="Seguimiento" icon="history">
                <ol class="space-y-3 text-sm">
                    <li class="flex gap-3"><x-icon name="check-circle" class="mt-0.5 text-emerald-600" /><span>Creada por {{ $t->usuario?->nombre_completo }}<span class="block text-xs text-slate-500">{{ $t->created_at->format('d/m/Y H:i') }}</span></span></li>
                    <li class="flex gap-3"><x-icon :name="$t->enviada_at ? 'check-circle' : 'x-circle'" :class="$t->enviada_at ? 'mt-0.5 text-emerald-600' : 'mt-0.5 text-slate-300'" /><span>Enviada desde {{ $t->origen?->nombre }}<span class="block text-xs text-slate-500">{{ $t->enviada_at?->format('d/m/Y H:i') ?? 'Pendiente' }}</span></span></li>
                    <li class="flex gap-3"><x-icon :name="$t->recibida_at ? 'check-circle' : 'x-circle'" :class="$t->recibida_at ? 'mt-0.5 text-emerald-600' : 'mt-0.5 text-slate-300'" /><span>Recibida en {{ $t->destino?->nombre }}<span class="block text-xs text-slate-500">{{ $t->recibida_at ? $t->recibida_at->format('d/m/Y H:i') . ' · ' . $t->receptor?->nombre_completo : 'Pendiente' }}</span></span></li>
                </ol>
                @if ($t->observacion)
                    <p class="mt-4 whitespace-pre-line border-t border-slate-100 pt-3 text-sm text-slate-600">{{ $t->observacion }}</p>
                @endif
            </x-card>

            @if ($enTransito)
                <x-card title="Recibir mercadería" icon="warehouse">
                    <x-textarea name="observacion" label="Observación de la recepción" rows="2" maxlength="1000" placeholder="Ej. Llegó una bolsa rota" />
                    <button type="submit" class="btn btn-primary mt-4 w-full"><x-icon name="check" /> Confirmar recepción</button>
                    <p class="mt-2 text-[11px] text-slate-500">Lo recibido entra al stock de {{ $t->destino?->nombre }}. Los faltantes quedan anotados.</p>
                </x-card>
            @endif
        </aside>
    </form>
@endsection
