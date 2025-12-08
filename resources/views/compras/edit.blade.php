@extends('layouts.app')

@section('content')
<div class="max-w-4xl mx-auto bg-white shadow rounded-2xl p-6 mt-4">

    <h1 class="text-2xl font-semibold text-gray-800 mb-4">✏️ Editar Compra</h1>

    <form method="POST" action="{{ route('compras.update', $compra->id) }}">
        @csrf
        @method('PUT')

        {{-- CABECERA --}}
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">

            {{-- Proveedor --}}
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Proveedor</label>
                <select name="proveedor_id" class="w-full border-gray-300 rounded-lg" required>
                    @foreach ($proveedores as $p)
                        <option value="{{ $p->id }}" @selected($p->id == $compra->proveedor_id)>
                            {{ $p->razon_social }}
                        </option>
                    @endforeach
                </select>
            </div>

            {{-- Tipo comprobante --}}
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Tipo comprobante</label>
                <select name="tipo_comprobante" class="w-full border-gray-300 rounded-lg" required>
                    <option value="FACTURA" @selected($compra->tipo_comprobante == 'FACTURA')>FACTURA</option>
                    <option value="BOLETA" @selected($compra->tipo_comprobante == 'BOLETA')>BOLETA</option>
                    <option value="NOTA" @selected($compra->tipo_comprobante == 'NOTA')>NOTA</option>
                </select>
            </div>

            {{-- Fecha --}}
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Fecha emisión</label>
                <input type="date" name="fecha_emision" class="w-full border-gray-300 rounded-lg"
                       value="{{ $compra->fecha_emision }}" required>
            </div>

            {{-- Serie --}}
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Serie</label>
                <input type="text" name="serie" value="{{ $compra->serie }}"
                       class="w-full border-gray-300 rounded-lg" required>
            </div>

            {{-- Número --}}
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Número</label>
                <input type="number" name="numero" value="{{ $compra->numero }}"
                       class="w-full border-gray-300 rounded-lg" required>
            </div>

            {{-- Moneda --}}
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Moneda</label>
                <select name="moneda" class="w-full border-gray-300 rounded-lg">
                    <option value="PEN" @selected($compra->moneda == 'PEN')>Soles (PEN)</option>
                    <option value="USD" @selected($compra->moneda == 'USD')>Dólares (USD)</option>
                </select>
            </div>
        </div>

        {{-- DETALLES --}}
        <h2 class="text-xl font-semibold text-gray-700 mb-3">📦 Detalles</h2>

        <table class="w-full border border-gray-300 rounded-lg text-sm" id="tablaItems">
            <thead class="bg-gray-100 text-left">
                <tr>
                    <th class="px-2 py-2">Producto</th>
                    <th class="px-2 py-2">Cant.</th>
                    <th class="px-2 py-2">Precio</th>
                    <th class="px-2 py-2">Lote</th>
                    <th class="px-2 py-2">Venc.</th>
                    <th class="px-2 py-2">Total</th>
                    <th class="px-2 py-2 text-center">X</th>
                </tr>
            </thead>

            <tbody id="detalleBody">
                
                {{-- Cargar detalles existentes --}}
                @foreach ($compra->detalles as $d)
                <tr>
                    <td class="px-2 py-1">
                        <select name="detalles[{{ $loop->index }}][producto_id]"
                                class="border-gray-300 rounded-lg w-full" required>

                            <option value="">Seleccione...</option>

                            @foreach ($productos as $p)
                                <option value="{{ $p->id }}" @selected($p->id == $d->producto_id)>
                                    {{ $p->nombre }}
                                </option>
                            @endforeach
                        </select>
                    </td>

                    <td class="px-2 py-1">
                        <input type="number" min="0.01" step="0.01"
                            name="detalles[{{ $loop->index }}][cantidad]"
                            value="{{ $d->cantidad }}"
                            class="border-gray-300 rounded-lg w-full"
                            oninput="calcularTotales()" required>
                    </td>

                    <td class="px-2 py-1">
                        <input type="number" min="0.01" step="0.01"
                            name="detalles[{{ $loop->index }}][precio_unitario]"
                            value="{{ $d->precio_unitario }}"
                            class="border-gray-300 rounded-lg w-full"
                            oninput="calcularTotales()" required>
                    </td>

                    <td class="px-2 py-1">
                        <input type="text"
                            name="detalles[{{ $loop->index }}][lote]"
                            value="{{ $d->kardexDetalle->lote ?? '' }}"
                            class="border-gray-300 rounded-lg w-full">
                    </td>

                    <td class="px-2 py-1">
                        <input type="date"
                            name="detalles[{{ $loop->index }}][fecha_vencimiento]"
                            value="{{ $d->kardexDetalle->fecha_vencimiento ?? '' }}"
                            class="border-gray-300 rounded-lg w-full">
                    </td>

                    <td class="px-2 py-1 font-semibold item-total">
                        S/ {{ number_format($d->total, 2) }}
                    </td>

                    <td class="px-2 py-1 text-center">
                        <button type="button"
                                onclick="this.closest('tr').remove(); calcularTotales();"
                                class="text-red-500 text-lg">✖</button>
                    </td>
                </tr>
                @endforeach

            </tbody>
        </table>

        <button type="button" onclick="agregarItem()"
            class="mt-3 bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded-lg shadow">
            ➕ Agregar producto
        </button>

        {{-- TOTALES --}}
        <div class="flex justify-end mt-6">
            <div class="text-right">
                <div class="text-gray-600 text-sm">Subtotal:</div>
                <div id="subtotalGeneral" class="font-semibold">S/ 0.00</div>

                <div class="text-gray-600 text-sm mt-2">Impuestos:</div>
                <div id="impuestoGeneral" class="font-semibold">S/ 0.00</div>

                <div class="text-gray-800 text-lg mt-3">Total:</div>
                <div id="totalGeneral" class="font-bold text-xl">S/ 0.00</div>

                <input type="hidden" name="subtotal">
                <input type="hidden" name="total_impuestos">
                <input type="hidden" name="total">
            </div>
        </div>

        {{-- OBSERVACIÓN --}}
        <div class="mt-6">
            <label class="block text-sm font-medium text-gray-700 mb-1">Observación</label>
            <textarea name="observacion" rows="3"
                class="w-full border-gray-300 rounded-lg">{{ $compra->observacion }}</textarea>
        </div>

        {{-- BOTÓN GUARDAR --}}
        <div class="mt-6 text-right">
            <button class="bg-green-600 hover:bg-green-700 text-white px-6 py-2 rounded-lg shadow">
                Guardar cambios
            </button>
        </div>
    </form>
</div>

<script>
    let productos = @json($productos);
    let filaIndex = {{ $compra->detalles->count() }};

    window.onload = calcularTotales;

    function agregarItem() {
        let tbody = document.getElementById('detalleBody');

        let fila = document.createElement('tr');

        fila.innerHTML = `
            <td class="px-2 py-1">
                <select name="detalles[${filaIndex}][producto_id]"
                    class="border-gray-300 rounded-lg w-full" required>
                    <option value="">Seleccione...</option>
                    ${productos.map(p => `<option value="${p.id}">${p.nombre}</option>`).join('')}
                </select>
            </td>

            <td class="px-2 py-1">
                <input type="number" min="0.01" step="0.01"
                    name="detalles[${filaIndex}][cantidad]"
                    class="border-gray-300 rounded-lg w-full"
                    oninput="calcularTotales()" required>
            </td>

            <td class="px-2 py-1">
                <input type="number" min="0.01" step="0.01"
                    name="detalles[${filaIndex}][precio_unitario]"
                    class="border-gray-300 rounded-lg w-full"
                    oninput="calcularTotales()" required>
            </td>

            <td class="px-2 py-1">
                <input type="text"
                    name="detalles[${filaIndex}][lote]"
                    class="border-gray-300 rounded-lg w-full">
            </td>

            <td class="px-2 py-1">
                <input type="date"
                    name="detalles[${filaIndex}][fecha_vencimiento]"
                    class="border-gray-300 rounded-lg w-full">
            </td>

            <td class="px-2 py-1 font-semibold item-total">S/ 0.00</td>

            <td class="px-2 py-1 text-center">
                <button type="button"
                        onclick="this.closest('tr').remove(); calcularTotales();"
                        class="text-red-500 text-lg">✖</button>
            </td>
        `;

        tbody.appendChild(fila);
        filaIndex++;
    }

    function calcularTotales() {
        let filas = document.querySelectorAll('#detalleBody tr');

        let subtotal = 0;
        let impuesto = 0;

        filas.forEach(fila => {
            let cantidad = parseFloat(fila.querySelector('input[name*="cantidad"]').value) || 0;
            let precio = parseFloat(fila.querySelector('input[name*="precio_unitario"]').value) || 0;

            let total = cantidad * precio;
            subtotal += total;

            fila.querySelector('.item-total').innerHTML = `S/ ${total.toFixed(2)}`;
        });

        impuesto = subtotal * 0.18;
        let totalGeneral = subtotal + impuesto;

        document.getElementById('subtotalGeneral').innerHTML = `S/ ${subtotal.toFixed(2)}`;
        document.getElementById('impuestoGeneral').innerHTML = `S/ ${impuesto.toFixed(2)}`;
        document.getElementById('totalGeneral').innerHTML = `S/ ${totalGeneral.toFixed(2)}`;

        // Inputs ocultos para el backend
        document.querySelector('input[name="subtotal"]').value = subtotal.toFixed(2);
        document.querySelector('input[name="total_impuestos"]').value = impuesto.toFixed(2);
        document.querySelector('input[name="total"]').value = totalGeneral.toFixed(2);
    }
</script>

@endsection
