@extends('layouts.app')

@section('title', 'Detalle del Producto')

@section('content')
<div class="min-h-screen bg-gray-100 py-10 px-4 flex flex-col items-center">

    {{-- 🧾 Contenedor principal --}}
    <div class="w-full max-w-5xl bg-white shadow-xl rounded-3xl p-8 border border-gray-200">

        {{-- 🔹 Encabezado --}}
        <div class="flex items-center gap-3 mb-8 border-b pb-5">
            <div class="bg-blue-100 text-blue-700 rounded-full p-3">📦</div>
            <div>
                <h1 class="font-extrabold text-gray-800">Detalle del Producto</h1>
                <p class="text-gray-500">Consulta la información completa del producto</p>
            </div>
        </div>

        {{-- 🧩 Información general --}}
        <div class="bg-gray-50 p-6 rounded-2xl shadow-inner mb-8">
            <h2 class="font-bold text-gray-700 mb-4 flex items-center gap-2">
                <span class="text-blue-500">ℹ️</span> Información General
            </h2>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 text-gray-800">
                <div><span class="font-semibold text-gray-700">Nombre:</span> {{ $producto->nombre }}</div>
                <div><span class="font-semibold text-gray-700">Código:</span> {{ $producto->codigo ?? '—' }}</div>
                <div><span class="font-semibold text-gray-700">Categoría:</span> {{ $producto->categoria->nombre ?? '—' }}</div>
                <div><span class="font-semibold text-gray-700">Tipo de Unidad:</span> {{ $producto->tipoUnidad->codigo ?? '—' }}</div>
            </div>
        </div>

        {{-- 💰 Detalles comerciales --}}
        <div class="bg-gray-50 p-6 rounded-2xl shadow-inner mb-8">
            <h2 class="font-bold text-gray-700 mb-4 flex items-center gap-2">
                <span class="text-green-500">💰</span> Detalles Comerciales
            </h2>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 text-gray-800">
                <div>
                    <span class="font-semibold text-gray-700">Precio:</span>
                    <span class="text-gray-900 font-bold">S/ {{ number_format($producto->precio_unitario, 2) }}</span>
                </div>
                <div>
                    <span class="font-semibold text-gray-700">Stock Disponible:</span>
                    <span class="font-semibold {{ $producto->stock > 0 ? 'text-green-700' : 'text-red-600' }}">
                        {{ $producto->stock ?? 0 }}
                    </span>
                </div>
                <div>
                    <span class="font-semibold text-gray-700">Tipo:</span>
                    @if($producto->es_servicio)
                        <span class="bg-blue-100 text-blue-700 px-3 py-1 rounded-xl font-semibold">Servicio</span>
                    @else
                        <span class="bg-green-100 text-green-700 px-3 py-1 rounded-xl font-semibold">Producto</span>
                    @endif
                </div>
                <div>
                    <span class="font-semibold text-gray-700">Estado:</span>
                    @if($producto->activo)
                        <span class="bg-green-100 text-green-700 px-3 py-1 rounded-xl font-semibold">Activo</span>
                    @else
                        <span class="bg-red-100 text-red-700 px-3 py-1 rounded-xl font-semibold">Inactivo</span>
                    @endif
                </div>
            </div>
        </div>

        {{-- 📝 Descripción --}}
        <div class="bg-gray-50 p-6 rounded-2xl shadow-inner mb-8">
            <h2 class="font-bold text-gray-700 mb-4 flex items-center gap-2">
                <span class="text-yellow-500">📝</span> Descripción
            </h2>

            <div class="text-gray-800 leading-relaxed">
                {{ $producto->descripcion ?: 'Sin descripción disponible.' }}
            </div>
        </div>

        {{-- 📅 Fechas --}}
        <div class="bg-gray-50 p-6 rounded-2xl shadow-inner mb-10">
            <h2 class="font-bold text-gray-700 mb-4 flex items-center gap-2">
                <span class="text-indigo-500">📅</span> Fechas del Registro
            </h2>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 text-gray-800">
                <div>
                    <span class="font-semibold">Creado el:</span>
                    <p class="mt-1 text-gray-700">{{ $producto->created_at?->format('d/m/Y H:i') ?? '—' }}</p>
                </div>
                <div>
                    <span class="font-semibold">Última actualización:</span>
                    <p class="mt-1 text-gray-700">{{ $producto->updated_at?->format('d/m/Y H:i') ?? '—' }}</p>
                </div>
            </div>
        </div>

        {{-- ⚙️ Acciones --}}
        <div class="flex flex-col sm:flex-row justify-end flex-wrap gap-4">
            <a href="{{ route('productos.edit', $producto) }}"
               class="flex-1 sm:flex-none text-center bg-yellow-500 hover:bg-yellow-600 text-white px-6 py-3 rounded-2xl font-semibold transition-all shadow-md">
                ✏️ Editar
            </a>

            <form action="{{ route('productos.destroy', $producto) }}" method="POST"
                  onsubmit="return confirm('¿Estás seguro de eliminar este producto?');"
                  class="flex-1 sm:flex-none">
                @csrf
                @method('DELETE')
                <button type="submit"
                        class="w-full bg-red-600 hover:bg-red-700 text-white px-6 py-3 rounded-2xl font-semibold transition-all shadow-md">
                    🗑️ Eliminar
                </button>
            </form>

            <a href="{{ route('productos.index') }}"
               class="flex-1 sm:flex-none text-center bg-blue-600 hover:bg-blue-700 text-white px-6 py-3 rounded-2xl font-semibold transition-all shadow-md">
                ← Volver
            </a>
        </div>
    </div>
</div>
@endsection
