@extends('layouts.app')

@section('title', 'Detalle de Categoría')

@section('content')
<div class="min-h-screen bg-gray-100 py-10 px-4 pb-28 flex flex-col items-center">

  {{-- Contenedor principal --}}
  <div class="w-full max-w-4xl bg-white shadow-xl rounded-3xl p-8 border border-gray-200">

    {{-- Encabezado --}}
    <div class="flex items-center gap-3 mb-8 border-b pb-5">
      <div class="bg-blue-100 text-blue-700 rounded-full p-3">📂</div>
      <div>
        <h1 class="font-extrabold text-gray-800">Detalle de la Categoría</h1>
        <p class="text-gray-500">Consulta la información completa de esta categoría</p>
      </div>
    </div>

    {{-- Información general --}}
    <div class="bg-gray-50 p-6 rounded-2xl shadow-inner mb-8">
      <h2 class="font-bold text-gray-700 mb-4 flex items-center gap-2">
        <span class="text-blue-500">ℹ️</span> Información General
      </h2>

      <div class="grid grid-cols-1 md:grid-cols-2 gap-6 text-gray-800">
        <div>
          <span class="font-semibold text-gray-700">Nombre:</span>
          <p>{{ $categoria->nombre }}</p>
        </div>
      </div>
    </div>

    {{-- Descripción --}}
    <div class="bg-gray-50 p-6 rounded-2xl shadow-inner mb-8">
      <h2 class="font-bold text-gray-700 mb-4 flex items-center gap-2">
        <span class="text-yellow-500">📝</span> Descripción
      </h2>
      <p class="text-gray-800 leading-relaxed">
        {{ $categoria->descripcion ?: 'Sin descripción disponible.' }}
      </p>
    </div>

    {{-- Fechas --}}
    <div class="bg-gray-50 p-6 rounded-2xl shadow-inner mb-10">
      <h2 class="font-bold text-gray-700 mb-4 flex items-center gap-2">
        <span class="text-indigo-500">📅</span> Fechas del Registro
      </h2>
      <div class="grid grid-cols-1 md:grid-cols-2 gap-6 text-gray-800">
        <div>
          <span class="font-semibold">Creado el:</span>
          <p class="mt-1 text-gray-700">{{ $categoria->created_at?->format('d/m/Y H:i') ?? '—' }}</p>
        </div>
        <div>
          <span class="font-semibold">Última actualización:</span>
          <p class="mt-1 text-gray-700">{{ $categoria->updated_at?->format('d/m/Y H:i') ?? '—' }}</p>
        </div>
      </div>
    </div>

    {{-- Acciones --}}
    <div class="flex flex-col sm:flex-row justify-end flex-wrap gap-4">
      <a href="{{ route('categorias.edit', $categoria) }}"
         class="flex-1 sm:flex-none text-center bg-yellow-500 hover:bg-yellow-600 text-white px-6 py-3 rounded-2xl font-semibold transition-all shadow-md">
        ✏️ Editar
      </a>

      <form action="{{ route('categorias.destroy', $categoria) }}" method="POST"
            onsubmit="return confirm('¿Estás seguro de eliminar esta categoría?');"
            class="flex-1 sm:flex-none">
        @csrf
        @method('DELETE')
        <button type="submit"
                class="w-full bg-red-600 hover:bg-red-700 text-white px-6 py-3 rounded-2xl font-semibold transition-all shadow-md">
          🗑️ Eliminar
        </button>
      </form>

      <a href="{{ route('categorias.index') }}"
         class="flex-1 sm:flex-none text-center bg-blue-600 hover:bg-blue-700 text-white px-6 py-3 rounded-2xl font-semibold transition-all shadow-md">
        ← Volver
      </a>
    </div>
  </div>
</div>
@endsection
