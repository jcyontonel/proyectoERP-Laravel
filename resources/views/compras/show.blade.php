@extends('layouts.app')

@section('content')

<div class="max-w-4xl mx-auto mt-6">

    {{-- BOTONES SUPERIORES --}}
    <div class="flex justify-between mb-4">
        <a href="{{ route('compras.index') }}"
           class="px-4 py-2 bg-gray-200 hover:bg-gray-300 rounded-lg shadow">
            ⬅️ Volver
        </a>

        <a href="{{ route('compras.edit', $compra->id) }}"
           class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg shadow">
            ✏️ Editar
        </a>
    </div>

    {{-- CARD PRINCIPAL --}}
    <div class="bg-white rounded-2xl shadow-lg p-6 border border-gray-200">

        {{-- TÍTULO --}}
        <h1 class="text-2xl font-semibold text-gray-800 mb-2">🧾 Detalle de Compra</h1>
        <p class="text-gray-500">Comprobante: <strong>{{ $compra->serie }}-{{ $compra->numero }}</strong></p>

        <hr class="my-4">

        {{-- INFORMACIÓN DE LA COMPRA --}}
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">

            <div>
                <h2 class="font-semibold text-gray-700 mb-1">📌 Información</h2>
                <p><strong>Tipo:</strong> {{ $compra->tipo_comprobante }}</p>
                <p><strong>Fecha emisión:</strong> {{ $compra->fecha_emision }}</p>
                <p><strong>Moneda:</strong> {{ $compra->moneda }}</p>
                <p><strong>Estado:</strong>
                    <span class="px-2 py-1 rounded-lg text-white
                        {{ $compra->estado === 'registrada' ? 'bg-green-600' : 'bg-gray-600' }}">
                        {{ ucfirst($compra->estado) }}
                    </span>
                </p>
            </div>

            <div>
                <h2 class="font-semibold text-gray-700 mb-1">🏢 Proveedor</h2>
                <p><strong>Razón social:</strong> {{ $compra->proveedor->razon_social }}</p>
                <p><strong>RUC/DNI:</strong> {{ $compra->proveedor->numero_documento }}</p>
                <p><strong>Teléfono:</strong> {{ $compra->proveedor->telefono ?? '-' }}</p>
                <p><strong>Email:</strong> {{ $compra->proveedor->email ?? '-' }}</p>
            </div>

        </div>

        <hr class="my-4">

        {{-- TABLA DE DETALLES --}}
        <h2 class="text-xl font-semibold text-gray-700 mb-3">📦 Detalle de Productos</h2>

        <div class="overflow-x-auto">
            <table class="w-full text-sm border border-gray-200 rounded-lg">
                <thead class="bg-gray-100 text-gray-700">
                    <tr>
                        <th class="px-3 py-2 text-left">Producto</th>
                        <th class="px-3 py-2 text-right">Cantidad</th>
                        <th class="px-3 py-2 text-right">P. Unitario</th>
                        <th class="px-3 py-2 text-left">Lote</th>
                        <th class="px-3 py-2 text-left">Vencimiento</th>
                        <th class="px-3 py-2 text-right">Total</th>
                    </tr>
                </thead>
                <tbody>

                    @foreach ($compra->detalles as $d)
                        <tr class="border-t">
                            <td class="px-3 py-2">{{ $d->producto->nombre }}</td>
                            <td class="px-3 py-2 text-right">{{ number_format($d->cantidad, 2) }}</td>
                            <td class="px-3 py-2 text-right">S/ {{ number_format($d->precio_unitario, 2) }}</td>

                            {{-- Lote y vencimiento desde kardex_detalle --}}
                            <td class="px-3 py-2">
                                {{ $d->kardexDetalle->lote ?? '-' }}
                            </td>

                            <td class="px-3 py-2">
                                {{ $d->kardexDetalle->fecha_vencimiento ?? '-' }}
                            </td>

                            <td class="px-3 py-2 text-right font-semibold">
                                S/ {{ number_format($d->total, 2) }}
                            </td>
                        </tr>
                    @endforeach

                </tbody>
            </table>
        </div>

        {{-- TOTALES --}}
        <div class="flex justify-end mt-6">
            <div class="text-right">
                <p class="text-gray-600">Subtotal:</p>
                <p class="font-semibold mb-2">S/ {{ number_format($compra->subtotal, 2) }}</p>

                <p class="text-gray-600">Impuestos (IGV):</p>
                <p class="font-semibold mb-2">S/ {{ number_format($compra->total_impuestos, 2) }}</p>

                <p class="text-gray-800 text-lg">Total:</p>
                <p class="font-bold text-2xl">S/ {{ number_format($compra->total, 2) }}</p>
            </div>
        </div>

        {{-- OBSERVACIÓN --}}
        @if ($compra->observacion)
        <div class="mt-6 bg-gray-50 border border-gray-200 p-4 rounded-lg">
            <h3 class="font-semibold text-gray-700 mb-1">📝 Observación</h3>
            <p class="text-gray-700">{{ $compra->observacion }}</p>
        </div>
        @endif

    </div>

</div>

@endsection
