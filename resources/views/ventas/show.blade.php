@extends('layouts.app')

@section('title', 'Venta Realizada')

@section('content')
<div class="min-h-screen bg-gray-100 flex flex-col items-center p-6">
    <div class="bg-white shadow-2xl rounded-2xl p-10 max-w-3xl w-full text-center flex flex-col justify-between">

        {{-- Mensaje principal --}}
        <div>
            <h1 class="text-5xl font-extrabold text-green-600 mb-6">
                ✅ ¡Venta realizada con éxito!
            </h1>

            <p class="text-2xl text-gray-700 mb-10">
                Tu venta ha sido registrada correctamente en el sistema.
            </p>

            {{-- Datos principales --}}
            <p class="font-semibold">{{ $venta->empresa->razon_social }}</p>
            <p><strong>RUC:</strong> {{ $venta->empresa->ruc }}</p>
            <br>

            <div class="grid grid-cols-2 gap-6 text-left text-gray-800 mb-8">
                
                {{-- Serie y Número --}}
                <p><strong>Documento:</strong> {{ $venta->serie }} - {{ $venta->numero }}</p>

                {{-- Columna izquierda (Cliente) --}}
                @if ($venta->cliente)
                    <p><strong>Cliente:</strong> {{ $venta->cliente->nombre }}</p>
                    <p><strong>N° Documento:</strong> {{ $venta->cliente->numero_documento }}</p>
                @else
                    <p><strong>Cliente:</strong> <span class="text-gray-500 italic">No registrado</span></p>
                @endif

                {{-- Columna derecha (Fecha) --}}
                <div>
                    <p><strong>Fecha:</strong> {{ $venta->created_at }}</p>
                </div>
            </div>

            {{-- Tabla de productos vendidos --}}
            <div class="overflow-x-auto mb-8">
                <table class="min-w-full border border-gray-300 rounded-xl overflow-hidden">
                    <thead class="bg-gray-200 text-gray-700">
                        <tr>
                            <th class="py-3 px-4 text-left">Producto</th>
                            <th class="py-3 px-4 text-center">Cantidad</th>
                            <th class="py-3 px-4 text-right">Subtotal</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($venta->detalles as $detalle)
                            <tr class="border-b align-top">
                                <td class="py-2 px-4 text-left">
                                    <span class="font-semibold text-gray-900">{{ $detalle->producto->nombre ?? '—' }}</span><br>
                                    <span class="text-gray-600 ">S/ {{ number_format($detalle->precio_unitario, 2) }}</span>
                                </td>
                                <td class="py-2 px-4 text-center">{{ $detalle->cantidad }}</td>
                                <td class="py-2 px-4 text-right">
                                    S/ {{ number_format($detalle->cantidad * $detalle->precio_unitario, 2) }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="3" class="py-4 text-center text-gray-500 italic">
                                    No hay productos en esta venta
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{-- Totales --}}
            <div class="text-right text-4xl font-semibold mb-8">
                <p>Total: <span class="text-green-600">S/ {{ number_format($venta->total, 2) }}</span></p>
            </div>
        </div>
        {{-- Botones de acción --}}
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mt-12">
            <a href="{{ route('ventas.create') }}" 
               class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-3 px-6 rounded-lg transition-all flex items-center justify-center">
               🛒 Nueva Venta
            </a>
        </div>
    </div>
</div>
@endsection
