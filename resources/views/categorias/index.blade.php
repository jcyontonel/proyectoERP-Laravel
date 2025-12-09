@extends('layouts.app')

@section('title', 'Categorías')

@section('content')
<div class="min-h-screen bg-gray-100 py-6 px-4 pb-28">
    <div class="max-w-5xl mx-auto bg-white shadow-lg rounded-3xl p-6">

        {{-- 🔹 Encabezado --}}
        <div class="flex items-center justify-between mb-6">
            <h1 class="font-extrabold text-gray-800 flex items-center gap-2">
                📂 Categorías
            </h1>
            <a href="{{ route('categorias.create') }}"
               class="bg-blue-600 hover:bg-blue-700 text-white font-semibold px-4 py-2 rounded-xl transition-all shadow-md">
                ➕ Nueva Categoría
            </a>
        </div>

        {{-- 🔍 Filtros --}}
        <div class="flex flex-col sm:flex-row gap-4 mb-6">
            <input id="searchInput" type="text" placeholder="Buscar por nombre..."
                   class="w-full sm:flex-1 border border-gray-300 rounded-xl px-4 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">

            <button id="clearFilter"
                    class="bg-gray-200 hover:bg-gray-300 text-gray-800 px-4 py-2 rounded-xl font-semibold transition-all">
                Limpiar
            </button>
        </div>

        {{-- 📋 Tabla --}}
        <div class="overflow-x-auto rounded-2xl shadow-inner border border-gray-200">
            <table class="min-w-full border-collapse">
                <thead class="bg-blue-600 text-white">
                    <tr>
                        <th class="px-4 py-3 text-left">#</th>
                        <th class="px-4 py-3 text-left">Nombre</th>
                        <th class="px-4 py-3 text-left">Descripción</th>
                        <th class="px-4 py-3 text-center">Acciones</th>
                    </tr>
                </thead>
                <tbody id="tablaCategorias" class="divide-y divide-gray-200 bg-white">
                    @forelse ($categorias as $categoria)
                        <tr class="hover:bg-blue-50 transition-all">
                            <td class="px-4 py-3">{{ $loop->iteration }}</td>
                            <td class="px-4 py-3 font-semibold text-gray-800">{{ $categoria->nombre }}</td>
                            <td class="px-4 py-3 text-gray-600">
                                {{ Str::limit($categoria->descripcion, 50) ?: '—' }}
                            </td>
                            <td class="px-4 py-3 text-center">
                                <a href="{{ route('categorias.show', $categoria) }}"
                                   class="inline-block bg-blue-500 hover:bg-blue-600 text-white px-3 py-2 rounded-xl shadow-md transition-all"
                                   title="Ver Detalle">
                                    🔍
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="text-center py-6 text-gray-500">
                                No hay categorías registradas.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- 📄 Paginación simulada --}}
        <div id="pagination" class="flex justify-center items-center gap-3 mt-6 flex-wrap"></div>

    </div>
</div>

{{-- 💡 Script de filtrado + paginación en frontend --}}
<script>
document.addEventListener('DOMContentLoaded', () => {
    const rows = Array.from(document.querySelectorAll('#tablaCategorias tr'));
    const searchInput = document.getElementById('searchInput');
    const pagination = document.getElementById('pagination');
    const clearFilter = document.getElementById('clearFilter');
    const rowsPerPage = 25;
    let currentPage = 1;
    let filteredRows = [...rows];

    function renderTable() {
        rows.forEach(row => row.style.display = 'none');
        const start = (currentPage - 1) * rowsPerPage;
        const end = start + rowsPerPage;
        filteredRows.slice(start, end).forEach(row => row.style.display = '');

        renderPagination();
    }

    function renderPagination() {
        pagination.innerHTML = '';
        const totalPages = Math.ceil(filteredRows.length / rowsPerPage);

        if (totalPages <= 1) return;

        for (let i = 1; i <= totalPages; i++) {
            const btn = document.createElement('button');
            btn.textContent = i;
            btn.className = `px-4 py-2 rounded-xl font-semibold shadow ${i === currentPage
                ? 'bg-blue-600 text-white'
                : 'bg-gray-200 hover:bg-gray-300 text-gray-800'
            }`;
            btn.onclick = () => { currentPage = i; renderTable(); };
            pagination.appendChild(btn);
        }
    }

    searchInput.addEventListener('input', () => {
        const term = searchInput.value.toLowerCase();
        filteredRows = rows.filter(row => row.innerText.toLowerCase().includes(term));
        currentPage = 1;
        renderTable();
    });

    clearFilter.addEventListener('click', () => {
        searchInput.value = '';
        filteredRows = [...rows];
        currentPage = 1;
        renderTable();
    });

    renderTable();
});
</script>
@endsection
