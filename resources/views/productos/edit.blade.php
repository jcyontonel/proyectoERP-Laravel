@extends('layouts.app')

@section('title', '✏️ Editar Producto')

@section('content')
<div class="min-h-screen bg-gray-100 flex justify-center py-10">
    <div class="bg-white w-full max-w-3xl rounded-3xl shadow-xl p-8 sm:p-10 md:p-12 mx-4">
        <h1 class="text-4xl md:text-5xl font-extrabold mb-10 text-center flex items-center justify-center gap-3">
            <span>✏️</span> Editar Producto
        </h1>

        <form action="{{ route('productos.update', $producto) }}" method="POST" class="space-y-8" style="padding: 0px 30px;">
            @csrf
            @method('PUT')

            @include('productos._form', ['producto' => $producto])

            <div class="flex flex-col sm:flex-row justify-between gap-4 pt-6">
                <a href="{{ route('productos.index') }}"
                   class="bg-gray-500 hover:bg-gray-600 text-white text-xl font-bold px-8 py-4 rounded-2xl shadow-md text-center transition">
                   ⬅️ Volver
                </a>
                <button type="submit"
                        class="bg-blue-600 hover:bg-blue-700 text-white text-xl font-bold px-10 py-4 rounded-2xl shadow-md transition">
                    💾 Actualizar
                </button>
            </div>
        </form>
    </div>
</div>
@endsection
