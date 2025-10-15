
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Login</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
</head>
<body class="bg-gray-100 min-h-screen flex items-center justify-center">
    <div class="w-full max-w-sm mx-auto">
        <div class="flex justify-center mt-8 mb-6">
            <img src="/favicon.ico" alt="Logo" class="w-20 h-20 rounded-full shadow-lg">
        </div>
        <h2 class="text-4xl font-bold text-center mb-10 text-gray-800">Iniciar Sesión</h2>

        @if(session('error'))
            <p class="text-red-500 text-center mb-4">{{ session('error') }}</p>
        @endif

        <form method="POST" action="{{ route('login.post') }}" class="space-y-8">
            @csrf
            <div>
                <label class="block text-xl font-medium text-gray-700 mb-3">Email</label>
                <input type="email" name="email" required class="w-full px-5 py-4 rounded-xl border border-gray-300 focus:outline-none focus:ring-2 focus:ring-blue-500 text-xl bg-white bg-opacity-80" placeholder="Correo electrónico">
            </div>
            <div>
                <label class="block text-xl font-medium text-gray-700 mb-3">Contraseña</label>
                <input type="password" name="password" required class="w-full px-5 py-4 rounded-xl border border-gray-300 focus:outline-none focus:ring-2 focus:ring-blue-500 text-xl bg-white bg-opacity-80" placeholder="Contraseña">
            </div>
            <button type="submit" class="w-full py-4 bg-blue-600 text-white text-2xl font-bold rounded-xl hover:bg-blue-700 transition">Ingresar</button>
        </form>

        <p class="mt-10 text-center text-gray-700 text-xl">¿No tienes cuenta? <a href="{{ route('register') }}" class="text-blue-600 font-semibold hover:underline">Registrarse</a></p>
    </div>
</body>
</html>
