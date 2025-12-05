@extends('layouts.app')

@section('content')
<div class="max-w-7xl mx-auto bg-white shadow rounded-2xl p-6 mt-4">
    <h1 class="text-2xl font-semibold text-gray-800 mb-4">📦 Compras</h1>

    {{-- Filtros --}}
    <div class="flex flex-col md:flex-row md:items-end md:justify-between gap-4 mb-6">
        <div class="flex flex-col md:flex-row md:items-center gap-4 w-full">
            {{-- Fecha --}}
            <div>
                <label for="fecha" class="block text-sm font-medium text-gray-700 mb-1">🗓️ Fecha</label>
                <input 
                    type="month"
                    id="fecha"
                    name="fecha"
                    class="border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500 w-full md:w-48"
                    value="{{ request('fecha', now()->format('Y-m')) }}"
                    onchange="filtrarCompras()"
                >
            </div>

            {{-- Buscador --}}
            <div class="flex-1">
                <label for="buscar" class="block text-sm font-medium text-gray-700 mb-1">🔍 Buscar</label>
                <input 
                    type="text" 
                    id="buscar" 
                    placeholder="Buscar por proveedor o número..." 
                    class="border-gray-300 rounded-lg w-full focus:ring-blue-500 focus:border-blue-500"
                    onkeyup="filtrarCompras()"
                >
            </div>
        </div>

        {{-- Botón de nueva compra --}}
        <div>
            <a href="{{ route('compras.create') }}" class="inline-flex items-center bg-blue-600 hover:bg-blue-700 text-white font-medium px-4 py-2 rounded-lg shadow transition">
                ➕ Nueva compra
            </a>
        </div>
    </div>

    {{-- Tabla --}}
    <div class="overflow-x-auto">
        <table class="min-w-full border border-gray-200 rounded-lg text-sm" id="tablaCompras">
            <thead class="bg-gray-100 text-gray-700 text-left">
                <tr>
                    <th class="px-3 py-2 border-b">Fecha</th>
                    <th class="px-3 py-2 border-b">Proveedor</th>
                    <th class="px-3 py-2 border-b">Comprobante</th>
                    <th class="px-3 py-2 border-b text-right">Subtotal</th>
                    <th class="px-3 py-2 border-b text-right">Total</th>
                    <th class="px-3 py-2 border-b text-center">Acciones</th>
                </tr>
            </thead>
            <tbody>
                @php $totalDia = 0; @endphp
                @foreach ($compras as $compra)
                    <tr class="border-b hover:bg-gray-50 fila-compra">
                        <td class="px-3 py-2">{{ \Carbon\Carbon::parse($compra->fecha_emision)->format('d/m/Y') }}</td>
                        <td class="px-3 py-2">{{ $compra->proveedor->razon_social ?? '—' }}</td>
                        <td class="px-3 py-2">
                            {{ strtoupper($compra->tipo_comprobante) }} <br>
                            <span class="text-gray-500 text-xs">{{ $compra->serie }}-{{ $compra->numero }}</span>
                        </td>
                        <td class="px-3 py-2 text-right">S/ {{ number_format($compra->subtotal, 2) }}</td>
                        <td class="px-3 py-2 text-right font-semibold">S/ {{ number_format($compra->total, 2) }}</td>
                        <td class="px-3 py-2 text-center">
                            <a href="{{ route('compras.show', $compra->id) }}" 
                               class="inline-flex items-center justify-center bg-green-100 hover:bg-green-200 text-green-700 rounded-full p-2 transition">
                                🔎
                            </a>
                        </td>
                    </tr>
                    @php $totalDia += $compra->total; @endphp
                @endforeach
            </tbody>
        </table>
    </div>

    {{-- Total general --}}
    <div class="flex justify-end mt-4">
        <div class="text-right bg-gray-50 rounded-lg p-3 border">
            <span class="text-gray-500 text-sm">Total de compras:</span><br>
            <span class="text-xl font-semibold text-gray-800">S/ {{ number_format($totalDia, 2) }}</span>
        </div>
    </div>

    {{-- Paginación --}}
    <div class="mt-6">
        {{ $compras->links() }}
    </div>
</div>

<script>
function filtrarCompras() {
    const buscar = document.getElementById('buscar').value.toLowerCase();
    const filas = document.querySelectorAll('.fila-compra');

    filas.forEach(fila => {
        const proveedor = fila.children[1].textContent.toLowerCase();
        const comprobante = fila.children[2].textContent.toLowerCase();
        const coincide = proveedor.includes(buscar) || comprobante.includes(buscar);
        fila.style.display = coincide ? '' : 'none';
    });
}
</script>
@endsection
