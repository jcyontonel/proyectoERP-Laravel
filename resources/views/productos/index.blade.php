@extends('layouts.app')

@section('title', '📦 Lista de Productos')

@section('content')
<div class="min-h-screen bg-gray-100 p-6">
    <h1 class="text-5xl font-extrabold mb-10 text-center">📦 Lista de Productos</h1>

    {{-- 🔧 Filtros superiores --}}
    <div class="flex flex-wrap items-center justify-between mb-6 gap-4">
        {{-- Botón Crear --}}
        <a href="{{ route('productos.create') }}"
           class="bg-green-600 hover:bg-green-700 text-white text-2xl font-bold px-8 py-3 rounded-xl shadow-lg transition-all duration-200">
           ➕ Nuevo Producto
        </a>

        {{-- Filtros dinámicos --}}
        <div class="flex flex-wrap items-center gap-4">
            {{-- Filtro Activo --}}
            <div>
                <label class="text-2xl font-semibold">Estado:</label>
                <select id="filtroActivo" 
                        class="text-2xl p-3 rounded-xl border-2 border-gray-300 focus:ring-4 focus:ring-blue-400 focus:outline-none">
                    <option value="todos" selected>Todos</option>
                    <option value="1">Activos</option>
                    <option value="0">Inactivos</option>
                </select>
            </div>

            {{-- Filtro Categoría --}}
            <div>
                <label class="text-2xl font-semibold">Categoría:</label>
                <select id="filtroCategoria"
                        class="text-2xl p-3 rounded-xl border-2 border-gray-300 focus:ring-4 focus:ring-blue-400 focus:outline-none">
                    <option value="todas" selected>Todas</option>
                    @foreach ($categorias as $categoria)
                        <option value="{{ strtolower($categoria->nombre) }}">{{ $categoria->nombre }}</option>
                    @endforeach
                </select>
            </div>
        </div>
    </div>

    {{-- Buscador --}}
    <div class="mb-8">
        <input 
            id="buscador"
            type="text"
            placeholder="🔍 Buscar por nombre o categoría..."
            class="w-full text-2xl p-4 rounded-xl border-2 border-gray-300 focus:ring-4 focus:ring-blue-400 focus:outline-none"
        >
    </div>

    {{-- Tabla --}}
    <div class="overflow-x-auto bg-white rounded-2xl shadow-xl p-6">
        <table class="min-w-full border-collapse text-xl" id="tablaProductos">
            <thead>
                <tr class="bg-blue-600 text-white text-center">
                    <th class="p-4">Nombre</th>
                    <th class="p-4">Categoría</th>
                    <th class="p-4">Precio</th>
                    <th class="p-4">Stock</th>
                    <th class="p-4">Acciones</th>
                </tr>
            </thead>
            <tbody id="cuerpoTabla">
                @foreach ($productos as $producto)
                    <tr class="border-b hover:bg-blue-50 text-center fila-producto"
                        data-activo="{{ $producto->activo ? '1' : '0' }}"
                        data-categoria="{{ strtolower($producto->categoria->nombre ?? 'sin categoría') }}">
                        <td class="p-4 nombre font-semibold">{{ $producto->nombre }}</td>
                        <td class="p-4 categoria">{{ $producto->categoria->nombre ?? 'Sin categoría' }}</td>
                        <td class="p-4">S/ {{ number_format($producto->precio_unitario, 2) }}</td>
                        <td class="p-4">{{ $producto->stock ?? 0 }}</td>
                        <td class="p-4">
                            <a href="{{ route('productos.show', $producto) }}" 
                               class="bg-blue-500 hover:bg-blue-600 text-white px-5 py-2 rounded-xl text-lg flex items-center justify-center gap-2 mx-auto w-fit">
                                🔍 Ver
                            </a>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        {{-- Paginador dinámico --}}
        <div id="paginador" class="mt-8 flex justify-center flex-wrap gap-3"></div>
    </div>
</div>

{{-- Script JS: filtros + búsqueda + paginación --}}
<script>
document.addEventListener('DOMContentLoaded', () => {
    const input = document.getElementById('buscador');
    const filtroActivo = document.getElementById('filtroActivo');
    const filtroCategoria = document.getElementById('filtroCategoria');
    const filas = Array.from(document.querySelectorAll('.fila-producto'));
    const cuerpo = document.getElementById('cuerpoTabla');
    const paginador = document.getElementById('paginador');

    const filasPorPagina = 25;
    let paginaActual = 1;

    [input, filtroActivo, filtroCategoria].forEach(e => {
        e.addEventListener('input', () => { paginaActual = 1; render(); });
        e.addEventListener('change', () => { paginaActual = 1; render(); });
    });

    function render() {
        const texto = input.value.toLowerCase().trim();
        const activoSel = filtroActivo.value;
        const categoriaSel = filtroCategoria.value.toLowerCase();

        const filasFiltradas = filas.filter(fila => {
            const nombre = fila.querySelector('.nombre').textContent.toLowerCase();
            const categoria = fila.dataset.categoria;
            const activo = fila.dataset.activo;

            const coincideTexto = nombre.includes(texto) || categoria.includes(texto);
            const coincideActivo = (activoSel === 'todos' || activo === activoSel);
            const coincideCategoria = (categoriaSel === 'todas' || categoria === categoriaSel);

            return coincideTexto && coincideActivo && coincideCategoria;
        });

        const totalPaginas = Math.ceil(filasFiltradas.length / filasPorPagina);
        const inicio = (paginaActual - 1) * filasPorPagina;
        const fin = inicio + filasPorPagina;

        cuerpo.innerHTML = '';
        filasFiltradas.slice(inicio, fin).forEach(fila => cuerpo.appendChild(fila));

        paginador.innerHTML = '';
        if (totalPaginas > 1) {
            for (let i = 1; i <= totalPaginas; i++) {
                const boton = document.createElement('button');
                boton.textContent = i;
                boton.className = `text-2xl px-4 py-2 rounded-xl shadow-md transition-all duration-200 ${
                    i === paginaActual ? 'bg-blue-600 text-white font-bold' : 'bg-gray-200 hover:bg-gray-300'
                }`;
                boton.addEventListener('click', () => {
                    paginaActual = i;
                    render();
                    window.scrollTo({ top: 0, behavior: 'smooth' });
                });
                paginador.appendChild(boton);
            }
        }
    }

    render();
});
</script>
@endsection
