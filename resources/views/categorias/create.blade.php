@extends('layouts.app')

@section('title', 'Registrar Categoría')

@section('content')
<div class="min-h-screen bg-gray-100 py-10 px-4 pb-28 flex flex-col items-center">
    <div class="w-full max-w-3xl bg-white shadow-xl rounded-3xl p-8 border border-gray-200">

        {{-- Encabezado --}}
        <div class="flex items-center gap-3 mb-8 border-b pb-5">
            <div class="bg-blue-100 text-blue-700 rounded-full p-3">➕</div>
            <div>
                <h1 class="font-extrabold text-gray-800">Registrar Categoría</h1>
                <p class="text-gray-500">Complete los datos de la nueva categoría</p>
            </div>
        </div>

        {{-- Formulario --}}
        <form action="{{ route('categorias.store') }}" method="POST">
            @include('categorias._form')
        </form>
    </div>
</div>
@endsection
