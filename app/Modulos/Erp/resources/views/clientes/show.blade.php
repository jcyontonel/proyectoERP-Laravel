{{--
    Ficha del cliente (ClienteController@show)
      $cliente (tipoDocumento)
      $resumen   ventas, vendido, ticket, ultima, primera, saldo (crédito pendiente)
      $favoritos productos más comprados (producto_id, descripcion, cantidad, importe, veces)
      $ventas    últimas 10 ventas en la empresa
--}}
@extends('layouts.app')

@use('App\Modulos\Catalogo\Support\Unidades')

@section('title', $cliente->nombre_completo)

@section('content')
    <x-page-header :title="$cliente->nombre_completo" :migas="['Clientes' => route('clientes.index'), $cliente->nombre_completo => null]"
        :subtitle="$cliente->documento_etiqueta . ($resumen['primera'] ? ' · Cliente desde ' . $resumen['primera']->translatedFormat('M Y') : '')">
        <x-slot:badge>
            <x-badge :tone="$cliente->activo ? 'success' : 'neutral'" :dot="true">{{ $cliente->activo ? 'Activo' : 'Inactivo' }}</x-badge>
            @if ($cliente->es_ruc)<x-badge tone="info" shape="box">Empresa</x-badge>@endif
        </x-slot:badge>
        <x-slot:acciones>
            @can('emitir-ventas')
                <a href="{{ route('ventas.create') }}" class="btn btn-secondary"><x-icon name="shopping-bag" class="text-brand-600" /> Nueva venta</a>
            @endcan
            @can('update', $cliente)
                <button type="button" data-eliminar="{{ route('clientes.destroy', $cliente) }}" data-nombre="{{ $cliente->nombre_completo }}" class="btn btn-secondary text-red-600 hover:bg-red-50">
                    <x-icon name="trash" /> Quitar
                </button>
                <a href="{{ route('clientes.edit', $cliente) }}" class="btn btn-primary"><x-icon name="pencil" /> Editar</a>
            @endcan
        </x-slot:acciones>
    </x-page-header>

    <x-flash />

    <section class="mt-6 grid grid-cols-2 gap-4 xl:grid-cols-4">
        <x-stat-card title="Total comprado" icon="dollar-sign" :value="'S/ ' . number_format($resumen['vendido'], 2)">
            <span>en tu empresa</span>
        </x-stat-card>
        <x-stat-card title="Compras" icon="receipt" tone="slate" :value="number_format($resumen['ventas'])">
            <span>ticket promedio <x-money :value="$resumen['ticket']" /></span>
        </x-stat-card>
        <x-stat-card title="Última compra" icon="calendar" tone="slate" :value="$resumen['ultima']?->translatedFormat('d M') ?? '—'">
            <span>{{ $resumen['ultima']?->diffForHumans() ?? 'Aún no compra' }}</span>
        </x-stat-card>
        <x-stat-card title="Saldo por cobrar" icon="coins" :tone="$resumen['saldo'] > 0 ? 'amber' : 'emerald'" :value="'S/ ' . number_format($resumen['saldo'], 2)">
            <span>{{ $resumen['saldo'] > 0 ? 'ventas al crédito pendientes' : 'sin deudas' }}</span>
        </x-stat-card>
    </section>

    <div class="mt-5 grid gap-5 lg:grid-cols-[minmax(0,1fr)_21rem] xl:grid-cols-[minmax(0,1fr)_24rem]">
        {{-- Últimas ventas --}}
        <x-card title="Últimas compras" icon="receipt" subtitle="Comprobantes emitidos a este cliente">
            @if ($ventas->isEmpty())
                <x-empty-state icon="receipt" title="Sin compras registradas" description="Las ventas a este cliente aparecerán aquí." class="py-6" />
            @else
                <div class="-mx-4 overflow-x-auto sm:-mx-5">
                    <table class="w-full text-sm">
                        <thead class="border-y border-slate-200 bg-slate-50/70 text-left text-xs font-medium text-slate-500">
                            <tr>
                                <th class="py-2.5 pl-4 pr-3 sm:pl-5">Comprobante</th>
                                <th class="px-3 py-2.5">Fecha</th>
                                <th class="px-3 py-2.5">Pago</th>
                                <th class="px-3 py-2.5 text-right">Total</th>
                                <th class="py-2.5 pl-3 pr-4 sm:pr-5">Estado</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach ($ventas as $v)
                                <tr>
                                    <td class="py-2.5 pl-4 pr-3 sm:pl-5">
                                        <a href="{{ $v->esBorrador() ? route('ventas.edit', $v) : route('ventas.show', $v) }}" class="font-semibold text-brand-600 hover:text-brand-700">{{ $v->numero_completo }}</a>
                                        <p class="text-[11px] text-slate-500">{{ $v->tipo_etiqueta }}</p>
                                    </td>
                                    <td class="whitespace-nowrap px-3 py-2.5 text-slate-600">{{ $v->fecha_emision?->format('d/m/Y H:i') }}</td>
                                    <td class="px-3 py-2.5"><x-metodo-pago :metodo="$v->esBorrador() ? null : $v->metodo_pago" :label="$v->metodo_label" /></td>
                                    <td class="px-3 py-2.5 text-right font-semibold"><x-money :value="$v->total" /></td>
                                    <td class="py-2.5 pl-3 pr-4 sm:pr-5"><x-estado-venta :estado="$v->estado" /></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </x-card>

        <aside class="space-y-5">
            <x-card title="Contacto" icon="user">
                <ul class="space-y-3 text-sm">
                    <li class="flex items-start gap-3"><x-icon name="phone" class="mt-0.5 text-slate-400" /> <span>{{ $cliente->telefono ?: 'Sin teléfono' }}</span></li>
                    <li class="flex items-start gap-3"><x-icon name="mail" class="mt-0.5 text-slate-400" /> <span class="break-all">{{ $cliente->correo ?: 'Sin correo' }}</span></li>
                    <li class="flex items-start gap-3"><x-icon name="map-pin" class="mt-0.5 text-slate-400" /> <span>{{ $cliente->direccion ?: 'Sin dirección' }}</span></li>
                </ul>
            </x-card>

            <x-card title="Productos favoritos" icon="sparkles" subtitle="Lo que más compra (por importe)">
                @forelse ($favoritos as $f)
                    <div class="flex items-center justify-between gap-3 border-b border-slate-100 py-2.5 text-sm last:border-0">
                        <div class="min-w-0">
                            <a href="{{ route('productos.show', $f->producto_id) }}" class="block truncate font-medium hover:text-brand-600">{{ $f->descripcion }}</a>
                            <p class="text-[11px] text-slate-500">{{ Unidades::cantidad($f->cantidad) }} unid. · {{ $f->veces }} {{ $f->veces == 1 ? 'vez' : 'veces' }}</p>
                        </div>
                        <span class="shrink-0 font-semibold"><x-money :value="$f->importe" /></span>
                    </div>
                @empty
                    <p class="text-sm text-slate-500">Aún no hay compras para analizar.</p>
                @endforelse
            </x-card>
        </aside>
    </div>

    @can('delete', $cliente)
        <x-dialogo-eliminar titulo="Quitar cliente" mensaje="Se quita de tu empresa; no es posible si tiene ventas registradas." />
    @endcan
@endsection
