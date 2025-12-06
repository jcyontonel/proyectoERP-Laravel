@extends('layouts.app')

@section('title', 'Registrar Venta')

@section('content')
<div class="min-h-screen bg-gray-100 p-6">
    <h1 class="text-5xl font-extrabold mb-10 text-center">🛒 Registrar Venta</h1>

    @if(session('success'))
        <div class="mb-8 p-6 bg-green-100 text-green-700 text-3xl font-bold rounded-2xl text-center shadow-lg">
            {{ session('success') }}
        </div>
    @endif

    @if(session('error'))
        <div class="mb-8 p-6 bg-red-100 text-red-700 text-3xl font-bold rounded-2xl text-center shadow-lg">
            {{ session('error') }}
        </div>
    @endif

    @if ($errors->any())
        <ul>
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    @endif

    <form id="venta-form" class="space-y-10 bg-white p-10 rounded-2xl shadow-2xl" 
        method="POST" action="{{ route('ventas.store') }}">
        @csrf

        <!-- Empresa (input oculto con la primera empresa por defecto) -->
        <input type="hidden" name="empresa_id" id="empresa_id" value="{{ $empresa ? $empresa->id : '' }}">

        <!-- Serie (input oculto con la primera serie de la empresa por defecto) -->
        <input type="hidden" name="serie" id="serie" value="{{ $empresa->correlativos ? $empresa->correlativos[0]->serie : '' }}">

        <!-- Cliente Autocompletar -->
        <div class="relative mb-4">
            <label for="cliente-input" class="block mb-4 text-4xl font-bold text-gray-900">Clientes registrados</label>
            <div class="relative">
                <input id="cliente-input" type="text" autocomplete="off"
                    placeholder="Buscar por nombre o DNI..."
                    class="bg-gray-50 border border-gray-400 text-gray-900 text-4xl rounded-2xl focus:ring-blue-500 focus:border-blue-500 block w-full p-6 h-32 pr-16">

                <!-- Botón X -->
                <button type="button" id="clear-cliente" class="absolute right-6 top-1/2 -translate-y-1/2 text-gray-400 hover:text-red-600 text-4xl font-bold hidden"
                    style="top:43px; right:30px;">✕
                </button>
            </div>
                <input type="hidden" name="cliente_id" id="cliente-id">
            <ul id="cliente-list" class="absolute z-10 w-full bg-white border border-gray-300 rounded-2xl mt-2 max-h-80 overflow-y-auto shadow-xl hidden"></ul>
            <label id="dni-label" class="block text-3xl font-semibold text-blue-700"></label>
        </div>

        <!-- Formulario para agregar/editar producto -->
        <div id="producto-form" class="mb-8">
            <!-- Producto Autocompletar -->
            <div class="relative mb-4">
                <label for="producto-input" class="block mb-4 text-4xl font-bold text-gray-900">Producto</label>

                <!--
                <input id="producto-input" type="text" autocomplete="off" placeholder="Buscar por nombre o codigo." class="bg-gray-50 border border-gray-400 text-gray-900 text-4xl rounded-2xl focus:ring-blue-500 focus:border-blue-500 block w-full p-6 h-32">
                -->

                <div class="relative">
                    <input id="producto-input" type="text" autocomplete="off"
                        placeholder="Buscar producto..."
                        class="bg-gray-50 border border-gray-400 text-gray-900 text-4xl rounded-2xl 
                            focus:ring-blue-500 focus:border-blue-500 block w-full p-6 h-32 pr-16">

                    <!-- Botón X -->
                    <button type="button" id="clear-producto"
                        class="absolute right-6 top-1/2 -translate-y-1/2 text-gray-400 hover:text-red-600 text-4xl font-bold hidden"
                        style="top:43px; right:30px;">
                        ✕
                    </button>
                </div>

                <input type="hidden" name="producto_id" id="producto-id">
                <ul id="producto-list" class="absolute z-10 w-full bg-white border border-gray-300 rounded-2xl mt-2 max-h-80 overflow-y-auto shadow-xl hidden"></ul>
            </div>
            <!-- Cantidad, Precio, Subtotal -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <div>
                    <label class="block mb-2 font-bold text-gray-900">Cantidad</label>
                    <input type="number" id="producto-cantidad" min="1" value="1" class="bg-gray-50 border border-gray-400 text-gray-900 text-3xl rounded-2xl w-full h-20 p-4">
                </div>
                <div>
                    <label class="block mb-2 font-bold text-gray-900">Precio</label>
                    <input type="text" id="producto-precio" readonly class="bg-gray-200 border border-gray-400 text-gray-900 text-3xl rounded-2xl w-full h-20 p-4">
                </div>
                <div>
                    <label class="block mb-2 font-bold text-gray-900">Subtotal</label>
                    <input type="text" id="producto-subtotal" readonly class="bg-gray-200 border border-gray-400 text-gray-900 text-3xl rounded-2xl w-full h-20 p-4">
                </div>
            </div>
        </div>
        <div class="flex gap-6 mb-10">
            <button type="button" id="add-producto" class="flex-1 text-white bg-green-600 hover:bg-green-700 focus:ring-4 focus:outline-none focus:ring-green-300 font-bold rounded-2xl text-4xl px-8 py-8 text-center shadow-lg">➕ Agregar Producto</button>
            <button type="button" id="reset-producto" class="flex-1 text-white bg-gray-500 hover:bg-gray-600 focus:ring-4 focus:outline-none focus:ring-gray-300 font-bold rounded-2xl text-4xl px-8 py-8 text-center shadow-lg">Limpiar</button>
        </div>
        <!-- Tabla de productos agregados -->
        <div class="overflow-x-auto mb-10">
            <table id="tabla-productos" class="min-w-full bg-white rounded-2xl shadow-lg">
                <thead>
                    <tr class="bg-blue-100">
                        <th class="px-6 py-4">Producto</th>
                        <th class="px-6 py-4"></th>
                        <th class="px-6 py-4">Acciones</th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>

        <!-- Total horizontal -->
        <div class="flex items-center gap-6 mb-10">
            <label class="text-4xl font-bold text-gray-900" for="total">Total</label>
            <input type="text" id="total" name="total" readonly
                   class="bg-gray-200 border border-gray-400 text-gray-900 text-5xl font-bold rounded-2xl h-32 p-6 w-full max-w-xs">
        </div>

        <!-- Inputs ocultos para enviar los productos -->
        <div id="productos-hidden"></div>

        <!-- Guardar -->
        <button type="submit"
            class="w-full text-white bg-blue-700 hover:bg-blue-800 focus:ring-4 focus:outline-none 
                focus:ring-blue-300 font-bold rounded-2xl text-5xl px-8 py-12 text-center shadow-2xl">
            💾 Guardar Venta
        </button>
    </form>
</div>

<!-- Autocompletar Cliente, scripts de productos y envío de la venta -->
<script>
document.addEventListener("DOMContentLoaded", () => {
    // --- Autocompletar Cliente ---
    const clientes = [
        @foreach($clientes as $cliente)
            {
                id: {{ $cliente->id }},
                nombre: "{{ addslashes($cliente->nombre) }}",
                dni: "{{ addslashes($cliente->numero_documento ?? '') }}"
            },
        @endforeach
    ];
    const inputCliente = document.getElementById('cliente-input');
    const listCliente = document.getElementById('cliente-list');
    const hiddenIdCliente = document.getElementById('cliente-id');
    inputCliente.addEventListener('input', function() {
        const value = this.value.trim().toLowerCase();
        listCliente.innerHTML = '';
        hiddenIdCliente.value = '';
        let dniLabel = document.getElementById('dni-label');
        dniLabel.textContent = '';
        if (value.length === 0) {
            listCliente.classList.add('hidden');
            return;
        }
        // Buscar por nombre o dni mientras escribe
        const filtered = clientes.filter(c =>
            c.nombre.toLowerCase().includes(value) || (c.dni && c.dni.toLowerCase().includes(value))
        );
        // Si el texto coincide exactamente con un cliente, mostrar el DNI
        const exact = clientes.find(c => c.nombre.toLowerCase() === value || (c.dni && c.dni.toLowerCase() === value));
        console.log('exact', exact);
        if (exact) {
            dniLabel.textContent = exact.dni ? `DNI: ${exact.dni}` : '';
            hiddenIdCliente.value = exact.id;
        }
        if (filtered.length === 0) {
            listCliente.classList.add('hidden');
            return;
        }
        filtered.forEach(c => {
            const li = document.createElement('li');
            li.className = 'cursor-pointer px-6 py-8 text-3xl hover:bg-blue-100 flex justify-between items-center';
            li.innerHTML = `<span>${c.nombre}</span> <span class="ml-6 text-blue-700 text-2xl">${c.dni ? c.dni : ''}</span>`;
            li.dataset.id = c.id;
            li.dataset.dni = c.dni;
            li.addEventListener('click', function() {
                inputCliente.value = c.nombre;
                hiddenIdCliente.value = c.id;
                dniLabel.textContent = c.dni ? `DNI: ${c.dni}` : '';
                listCliente.classList.add('hidden');
            });
            listCliente.appendChild(li);
        });
        listCliente.classList.remove('hidden');
    });

    document.addEventListener('click', function(e) {
        if (!inputCliente.contains(e.target) && !listCliente.contains(e.target)) {
            listCliente.classList.add('hidden');
        }
    });
    // Botón para limpiar cliente seleccionado
    const clearClienteBtn = document.getElementById('clear-cliente');

    inputCliente.addEventListener('input', () => {
        // Mostrar botón X si hay texto
        clearClienteBtn.classList.toggle('hidden', inputCliente.value.trim() === '');
    });

    // Al hacer clic en X, limpiar todo
    clearClienteBtn.addEventListener('click', () => {
        inputCliente.value = '';
        hiddenIdCliente.value = '';
        document.getElementById('dni-label').textContent = '';
        clearClienteBtn.classList.add('hidden');
    });

    // --- Autocompletar Productos ---
    const productos = [
        @foreach($productos as $producto)
            {
                id: {{ $producto->id }},
                nombre: "{{ addslashes($producto->nombre) }}",
                codigo: "{{ addslashes($producto->codigo ?? '') }}",
                precio_unitario: {{ $producto->precio_unitario }},
                stock: {{ $producto->stock }}
            },
        @endforeach
    ];
    const inputProducto = document.getElementById('producto-input');
    const listProducto = document.getElementById('producto-list');
    const hiddenIdProducto = document.getElementById('producto-id');
    const stockLabel = document.getElementById('stock-label');
    const clearProductoBtn = document.getElementById('clear-producto');

    inputProducto.addEventListener('input', function() {
        const value = this.value.trim().toLowerCase();
        listProducto.innerHTML = '';
        hiddenIdProducto.value = '';
        if (value.length === 0) {
            listProducto.classList.add('hidden');
            return;
        }
        // Buscar por nombre o codigo mientras escribe
        const filtered = productos.filter(p =>
            p.nombre.toLowerCase().includes(value) || (p.codigo && p.codigo.toLowerCase().includes(value))
        );
        // Si el texto coincide exactamente con un producto, mostrar el precio
        const exact = productos.find(p => p.nombre.toLowerCase() === value || (p.codigo && p.codigo.toLowerCase() === value));
        if (exact) {
            hiddenIdProducto.value = exact.id;
        }
        if (filtered.length === 0) {
            listProducto.classList.add('hidden');
            return;
        }
        filtered.forEach(p => {
            const li = document.createElement('li');
            li.className = 'cursor-pointer px-6 py-8 text-3xl hover:bg-blue-100 flex justify-between items-center';
            li.innerHTML = `<span>${p.nombre}</span> <span class="ml-6 text-blue-700 text-2xl">$${p.precio_unitario.toFixed(2)}</span>`;
            li.dataset.id = p.id;
            li.dataset.precio = p.precio_unitario;
            li.addEventListener('click', function() {
                inputProducto.value = p.nombre;
                hiddenIdProducto.value = p.id;
                const precio = p.precio_unitario.toFixed(2);
                // Actualizar precio y subtotal al seleccionar producto o cantidad
                document.getElementById('producto-precio').value = precio;
                const cantidad = parseFloat(document.getElementById('producto-cantidad').value) || 1;
                document.getElementById('producto-subtotal').value = (precio * cantidad).toFixed(2);
                listProducto.classList.add('hidden');
            });
            listProducto.appendChild(li);
        });
        listProducto.classList.remove('hidden');
        clearProductoBtn.classList.toggle('hidden', this.value.trim() === '');
    });

    document.addEventListener('click', function(e) {
        if (!inputProducto.contains(e.target) && !listProducto.contains(e.target)) {
            listProducto.classList.add('hidden');
        }
    });

    // --- Scripts de productos con tabla ---
    let productosVenta = [];
    let editIndex = null;

    // Actualizar precio y subtotal al seleccionar producto o cantidad
    document.getElementById('producto-cantidad').addEventListener('input', function() {
        const precio = parseFloat(document.getElementById('producto-precio').value) || 0;
        const cantidad = parseFloat(this.value) || 1;
        document.getElementById('producto-subtotal').value = (precio * cantidad).toFixed(2);
    });

    // Agregar o actualizar producto en la lista
    document.getElementById('add-producto').addEventListener('click', function() {
        const cantidad = parseFloat(document.getElementById('producto-cantidad').value) || 1;
        const precio = parseFloat(document.getElementById('producto-precio').value) || 0;
        const subtotal = parseFloat(document.getElementById('producto-subtotal').value) || 0;
        const productoNombre = inputProducto.value;
        const productoId = hiddenIdProducto.value;
        if (!productoId || cantidad < 1) return;
        const productoObj = { id: productoId, nombre: productoNombre, cantidad, precio, subtotal };
        if (editIndex !== null) {
            productosVenta[editIndex] = productoObj;
            editIndex = null;
        } else {
            productosVenta.push(productoObj);
        }
        renderTablaProductos();
        resetProductoForm();
    });

    // Limpiar formulario producto
    document.getElementById('reset-producto').addEventListener('click', function() {
        resetProductoForm();
    });

    function resetProductoForm() {
        inputProducto.value = '';
        hiddenIdProducto.value = '';
        document.getElementById('producto-cantidad').value = 1;
        document.getElementById('producto-precio').value = '';
        document.getElementById('producto-subtotal').value = '';
        editIndex = null;
        const btn = document.getElementById('add-producto');
        btn.textContent = '➕ Agregar Producto';
        btn.classList.remove('bg-yellow-500', 'hover:bg-yellow-600');
        btn.classList.add('bg-green-600', 'hover:bg-green-700');
        listProducto.classList.add('hidden');
    }

    // Renderizar tabla de productos
    function renderTablaProductos() {
        const tbody = document.querySelector('#tabla-productos tbody');
        tbody.innerHTML = '';
        let total = 0;
        productosVenta.forEach((p, i) => {
            total += p.subtotal;
            const tr = document.createElement('tr');
            tr.className = 'border-b';
            tr.innerHTML = `
                <td class="px-6 py-4">
                    <div class="font-bold">${p.nombre}</div>
                    <div class="text-gray-600 text-3xl">💲Precio: ${p.precio.toFixed(2)} </div>
                </td>
                <td class="px-6 py-4 text-right">
                    <div class="font-bold"> ${p.subtotal.toFixed(2)}</div>
                    <div class="text-gray-600 text-3xl">Cantidad: ${p.cantidad}</div>
                </td>
                <td class="px-6 py-4 flex gap-4 justify-end">
                    <button type="button" 
                        class="editar-producto bg-yellow-400 hover:bg-yellow-500 text-white font-bold px-6 py-3 rounded-xl" 
                        data-index="${i}">✏️
                    </button>
                    <button type="button" 
                        class="eliminar-producto bg-red-600 hover:bg-red-700 text-white font-bold px-6 py-3 rounded-xl" 
                        data-index="${i}">🗑
                    </button>
                </td>
            `;
            tbody.appendChild(tr);
        });
        document.getElementById('total').value = total.toFixed(2);
    }

    // Editar producto
    document.querySelector('#tabla-productos').addEventListener('click', function(e) {
        if (e.target.classList.contains('editar-producto')) {
            const idx = parseInt(e.target.dataset.index);
            const p = productosVenta[idx];
            hiddenIdProducto.value = p.id;
            inputProducto.value = p.nombre;
            //
            document.getElementById('producto-cantidad').value = p.cantidad;
            document.getElementById('producto-precio').value = p.precio;
            document.getElementById('producto-subtotal').value = p.subtotal;
            editIndex = idx;
            const btn = document.getElementById('add-producto');
            btn.textContent = '✏️ Actualizar Producto';
            btn.classList.remove('bg-green-600', 'hover:bg-green-700');
            btn.classList.add('bg-yellow-500', 'hover:bg-yellow-600');
        }
        if (e.target.classList.contains('eliminar-producto')) {
            const idx = parseInt(e.target.dataset.index);
            productosVenta.splice(idx, 1);
            renderTablaProductos();
            resetProductoForm();
        }
    });

    clearProductoBtn.addEventListener('click', () => {
        resetProductoForm();
        clearProductoBtn.classList.add('hidden');
    });

    // --- Enviar venta por formulario tradicional ---
    document.getElementById('venta-form').addEventListener('submit', function(e) {
        // Antes de enviar, agrega los productos como inputs ocultos
        const productosHidden = document.getElementById('productos-hidden');
        productosHidden.innerHTML = '';
        productosVenta.forEach((p, i) => {
            productosHidden.innerHTML += `
                <input type="hidden" name="productos[${i}][id]" value="${p.id}">
                <input type="hidden" name="productos[${i}][cantidad]" value="${p.cantidad}">
                <input type="hidden" name="productos[${i}][precio]" value="${p.precio}">
                <input type="hidden" name="productos[${i}][subtotal]" value="${p.subtotal}">
            `;
        });
    });



});
</script>
@endsection
