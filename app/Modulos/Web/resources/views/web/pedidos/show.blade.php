{{-- Pedido web (PedidoController@show): $pedido (detalles.producto, zona, venta), $metodos --}}
@extends('layouts.app')

@php
    use App\Modulos\Web\Models\PedidoWeb;
    $telefono = preg_replace('/\D/', '', $pedido->cliente_telefono);
    $whatsapp = strlen($telefono) === 9 ? "51{$telefono}" : $telefono;   // celular peruano sin código de país
@endphp

@section('title', 'Pedido ' . $pedido->codigo())

@section('content')
    <x-page-header :title="'Pedido ' . $pedido->codigo()" :migas="['Página web' => route('web.tienda.edit'), 'Pedidos' => route('web.pedidos.index'), $pedido->codigo() => null]"
        :subtitle="'Recibido el ' . $pedido->created_at->format('d/m/Y \a \l\a\s H:i')">
        <x-slot:acciones>
            @if ($pedido->venta)
                <a href="{{ route('ventas.show', $pedido->venta) }}" class="btn btn-secondary"><x-icon name="receipt" class="text-brand-600" /> {{ $pedido->venta->numero_completo }}</a>
            @elseif ($pedido->esConvertible())
                @ruta('ventas.create')
                    <form method="POST" action="{{ route('web.pedidos.convertir', $pedido) }}">
                        @csrf
                        <button type="submit" class="btn btn-primary"><x-icon name="receipt" /> Convertir en venta</button>
                    </form>
                @endruta
            @endif
        </x-slot:acciones>
    </x-page-header>

    <x-flash />

    <div class="mt-6 grid gap-5 lg:grid-cols-[minmax(0,1fr)_20rem]">
        <x-card title="Productos" icon="package">
            <table class="w-full text-sm">
                <thead class="text-left text-xs text-slate-500"><tr><th class="pb-2">Producto</th><th class="pb-2 text-right">Cant.</th><th class="pb-2 text-right">P. unit.</th><th class="pb-2 text-right">Total</th></tr></thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach ($pedido->detalles as $d)
                        <tr><td class="py-2">{{ $d->descripcion }}</td><td class="py-2 text-right">{{ rtrim(rtrim(number_format($d->cantidad, 3), '0'), '.') }}</td>
                            <td class="py-2 text-right"><x-money :value="$d->precio_unitario" /></td><td class="py-2 text-right font-medium"><x-money :value="$d->total" /></td></tr>
                    @endforeach
                </tbody>
            </table>
            <dl class="ml-auto mt-4 w-64 space-y-1 text-sm">
                <div class="flex justify-between"><dt class="text-slate-500">Subtotal</dt><dd><x-money :value="$pedido->subtotal" /></dd></div>
                <div class="flex justify-between"><dt class="text-slate-500">Envío</dt><dd><x-money :value="$pedido->costo_envio" /></dd></div>
                <div class="flex justify-between border-t border-slate-200 pt-1 text-base font-bold"><dt>Total</dt><dd><x-money :value="$pedido->total" /></dd></div>
            </dl>
            @if ($pedido->notas)<p class="mt-4 rounded-lg bg-slate-50 p-3 text-sm"><strong>Notas del cliente:</strong> {{ $pedido->notas }}</p>@endif
        </x-card>

        <aside class="space-y-5">
            <x-card title="Estado" icon="list-checks">
                <x-badge :tone="$pedido->tono()" dot>{{ $pedido->etiqueta() }}</x-badge>
                @if (PedidoWeb::FLUJO[$pedido->estado])
                    <form method="POST" action="{{ route('web.pedidos.estado', $pedido) }}" class="mt-4 flex flex-wrap gap-2">
                        @csrf @method('PATCH')
                        @foreach (PedidoWeb::FLUJO[$pedido->estado] as $siguiente)
                            <button name="estado" value="{{ $siguiente }}" @class(['btn', 'btn-secondary' => $siguiente === PedidoWeb::CANCELADO, 'btn-primary' => $siguiente !== PedidoWeb::CANCELADO])
                                @if ($siguiente === PedidoWeb::CANCELADO) onclick="return confirm('¿Cancelar el pedido?')" @endif>
                                {{ ['confirmado' => 'Confirmar', 'enviado' => 'Marcar en camino', 'entregado' => 'Marcar entregado', 'cancelado' => 'Cancelar'][$siguiente] }}
                            </button>
                        @endforeach
                    </form>
                @endif
            </x-card>

            <x-card title="Cliente" icon="user">
                <p class="font-medium">{{ $pedido->cliente_nombre }}</p>
                <p class="text-sm text-slate-600">{{ $pedido->cliente_telefono }}
                    <a href="https://wa.me/{{ $whatsapp }}?text={{ rawurlencode("Hola {$pedido->cliente_nombre}, te escribimos por tu pedido {$pedido->codigo()}.") }}" target="_blank" rel="noopener" class="ml-1 text-xs font-semibold text-emerald-600 hover:underline">WhatsApp</a></p>
                @if ($pedido->cliente_correo)<p class="text-sm text-slate-600">{{ $pedido->cliente_correo }}</p>@endif
                @if ($pedido->cliente_documento)<p class="text-sm text-slate-600">{{ ['1' => 'DNI', '6' => 'RUC', '4' => 'CE'][$pedido->cliente_tipo_doc] ?? 'Doc.' }} {{ $pedido->cliente_documento }}</p>@endif
            </x-card>

            <x-card title="Entrega y pago" icon="truck">
                <dl class="space-y-2 text-sm">
                    <div><dt class="text-xs text-slate-500">Entrega</dt>
                        <dd>{{ $pedido->entrega === 'envio' ? 'Envío · ' . ($pedido->zona?->nombre ?? 'zona eliminada') : 'Recojo en tienda' }}</dd></div>
                    @if ($pedido->direccion)<div><dt class="text-xs text-slate-500">Dirección</dt><dd>{{ $pedido->direccion }}@if ($pedido->referencia)<br><span class="text-slate-500">{{ $pedido->referencia }}</span>@endif</dd></div>@endif
                    <div><dt class="text-xs text-slate-500">Pago</dt><dd>{{ $metodos[$pedido->metodo_pago] ?? $pedido->metodo_pago }} ·
                        @if ($pedido->pago_estado === 'pagado')<span class="font-semibold text-emerald-700">pagado</span>@else<span class="text-amber-700">por cobrar</span>@endif</dd></div>
                    @if ($pedido->culqi_cargo_id)<div><dt class="text-xs text-slate-500">Cargo Culqi</dt><dd class="break-all text-xs">{{ $pedido->culqi_cargo_id }}</dd></div>@endif
                </dl>
            </x-card>
        </aside>
    </div>
@endsection
