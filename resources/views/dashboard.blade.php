@extends('layouts.app')

@section('title', 'Dashboard')

@section('content')
<div class="flex flex-col p-4">

    <h1 class="text-5xl font-bold mt-5 mb-15 text-center">📊 Panel Principal</h1>

    <!-- Grid de opciones -->
    <div class="grid grid-cols-1 sm:grid-cols-1 gap-4">

        <!-- Card Ventas -->
        <a href="{{ url('/ventas/create') }}" class="btn-dashboard bg-blue-400">
            <h2> 🛒 Nueva Venta </h2>
        </a>

        <!-- Card Compras 
        <a href="{{ url('/compras/create') }}" class="btn-dashboard bg-green-400">
            <h2> 📦 Nueva Compras </h2>
        </a>
        -->
        <!-- Card Productos -->
        <a href="{{ url('/productos') }}" class="btn-dashboard bg-red-400">
            <h2> 📋 Productos </h2>
        </a>

        <!-- Card Categorias -->
        <a href="{{ url('/categorias') }}" class="btn-dashboard bg-purple-400">
            <h2> 🗂️ Categorías </h2>
        </a>

    </div>

    <!-- Botón Logout (Flowbite button) -->
    <form method="POST" action="{{ route('logout') }}" class="mt-8 w-full" style="margin-top: 15rem;">
        @csrf
        <button type="submit"
                class="w-full text-white bg-gray-800 hover:bg-gray-900 focus:ring-4 focus:outline-none 
                       focus:ring-gray-300 font-medium rounded-lg px-5 py-6 text-center shadow">
            🚪 Cerrar Sesión
        </button>
    </form>

</div>
@endsection
