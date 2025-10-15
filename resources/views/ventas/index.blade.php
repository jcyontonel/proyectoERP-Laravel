@extends('layouts.app')

@section('title', '📅 Lista de Ventas')

@section('content')
<div class="min-h-screen bg-gray-100 p-6">
    <h1 class="text-5xl font-extrabold mb-10 text-center">📋 Lista de Ventas</h1>

    {{-- 🔍 Filtros: una sola fila (scroll horizontal si hace falta) --}}
    <form method="GET" action="{{ route('ventas.index') }}" class="mb-8">
        <div class="bg-white rounded-2xl shadow-lg p-4 overflow-x-auto">
            <div class="flex items-center gap-4 whitespace-nowrap px-2">
                {{-- Fecha (un día — últimos 7 días) --}}
                <div class="flex-shrink-0">
                    <label for="fecha" class="block text-sm sm:text-base font-semibold mb-1">Seleccionar día</label>
                    <input type="date" id="fecha" name="fecha"
                        value="{{ request('fecha', now()->format('Y-m-d')) }}"
                        max="{{ now()->format('Y-m-d') }}"
                        min="{{ now()->subDays(7)->format('Y-m-d') }}"
                        class="text-base sm:text-lg p-2 sm:p-3 rounded-xl border-2 border-gray-300 focus:ring-4 focus:ring-blue-400 focus:outline-none w-56"
                    >
                </div>

                {{-- Estado --}}
                <div class="flex-shrink-0">
                    <label for="estado" class="block text-sm sm:text-base font-semibold mb-1">Estado</label>
                    <select
                        name="estado"
                        id="estado"
                        class="text-base sm:text-lg p-2 sm:p-3 rounded-xl border-2 border-gray-300 focus:ring-4 focus:ring-blue-400 focus:outline-none w-56"
                    >
                        <option value="Registrada" {{ request('estado', 'Registrada') == 'Registrada' ? 'selected' : '' }}>Registrada</option>
                        <option value="Emitida" {{ request('estado') == 'Emitida' ? 'selected' : '' }}>Emitida</option>
                        <option value="Anulada" {{ request('estado') == 'Anulada' ? 'selected' : '' }}>Anulada</option>
                    </select>
                </div>

                {{-- Botón Buscar --}}
                <div class="flex-shrink-0">
                    <label class="block text-sm sm:text-base mb-1 invisible">Acción</label>
                    <button
                        type="submit"
                        class="text-base sm:text-lg bg-blue-600 hover:bg-blue-700 text-white font-bold px-6 py-2 rounded-xl shadow-lg transition-all duration-200"
                    >
                        🔍 Buscar
                    </button>
                </div>
            </div>
        </div>
    </form>


    {{-- 📄 Tabla de ventas --}}
    <div class="overflow-x-auto bg-white rounded-2xl shadow-xl p-6">
        <table class="min-w-full border-collapse text-xl">
            <thead>
                <tr class="bg-blue-600 text-white text-center">
                    <th class="p-4">#</th>
                    <th class="p-4">Cliente</th>
                    <th class="p-4">Usuario</th>
                    <th class="p-4">Fecha Emisión</th>
                    <th class="p-4">Total</th>
                    <th class="p-4">Acciones</th>
                </tr>
            </thead>
            <tbody>
                @php $sumaTotal = 0; @endphp

                @forelse ($ventas as $venta)
                    @php $sumaTotal += $venta->total; @endphp
                    <tr class="border-b hover:bg-blue-50 text-center">
                        <td class="p-4 font-semibold">{{ $venta->id }}</td>
                        <td class="p-4">{{ $venta->cliente->nombre ?? '-' }}</td>
                        <td class="p-4">{{ $venta->user->name ?? '-' }}</td>
                        <td class="p-4">{{ \Carbon\Carbon::parse($venta->fecha_emision)->format('d/m/Y H:i') }}</td>
                        <td class="p-4 font-bold text-green-600">S/ {{ number_format($venta->total, 2) }}</td>
                        <td class="p-4">
                            <a href="{{ route('ventas.show', $venta->id) }}" 
                               class="bg-blue-500 hover:bg-blue-600 px-5 py-2 rounded-xl text-lg flex items-center justify-center gap-2">
                                🔍 Ver
                            </a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="p-6 text-center text-2xl text-gray-500">
                            No hay ventas registradas en esta fecha.
                        </td>
                    </tr>
                @endforelse

                {{-- Total del día --}}
                @if(count($ventas) > 0)
                    <tr class="bg-gray-100 font-bold text-2xl text-center">
                        <td colspan="4" class="p-4 text-right">Total del día:</td>
                        <td colspan="2" class="p-4 text-green-700">S/ {{ number_format($sumaTotal, 2) }}</td>
                    </tr>
                @endif
            </tbody>
        </table>
    </div>
</div>
@endsection
