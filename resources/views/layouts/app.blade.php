<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>@yield('title', 'Mi App')</title>
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
    <script src="{{ asset('js/app.js') }}"></script>
</head>
<body class="flex flex-col min-h-screen bg-blue-100">

    <!-- Encabezado -->
    <header class="bg-blue-600 text-white p-4 flex justify-between items-center shadow-md">
        <h1 class="font-bold">📊 Mi Sistema</h1>
    </header>

    <!-- Contenido principal -->
    <main class="flex-1 p-4" style="padding-bottom: 10rem;">
        @yield('content')
    </main>

    <!-- Footer tipo barra de navegación inferior -->
    <footer class="bg-blue-400 border-t shadow-inner p-6 fixed bottom-0 left-0 w-full flex justify-around">
        <a href="{{ url('/dashboard') }}" class="flex flex-col items-center text-gray-700 hover:text-blue-600">
            🏠 <span>Inicio</span>
        </a>
        <a href="{{ url('/ventas') }}" class="flex flex-col items-center text-gray-700 hover:text-blue-600">
            🛒 <span>Ventas</span>
        </a>
        <a href="{{ url('/productos') }}" class="flex flex-col items-center text-gray-700 hover:text-blue-600">
            📦 <span>Compras</span>
        </a>
        <a href="{{ url('/usuarios') }}" class="flex flex-col items-center text-gray-700 hover:text-blue-600">
            👤 <span>Usuarios</span>
        </a>
    </footer>

</body>
</html>
