
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Login</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
</head>

<body class="bg-blue-100 min-h-screen flex items-center justify-center">

    <div class="w-full mx-auto">

        <div class="flex justify-center mt-8 mb-6">
            <img src="{{ asset('images/logo.png') }}" alt="Logo" class="logo rounded-full shadow-lg">
        </div>
        <h2 class="text-3xl font-bold text-center mb-4 text-gray-800">Iniciar Sesión</h2>

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
            <div class="flex items-center">
                <input id="remember" type="checkbox" name="remember" checked class="h-5 w-5 text-blue-600">
                <label for="remember" class="ml-2 text-lg text-gray-700">Recordar sesión</label>
            </div>

            <button type="submit" class="w-full py-4 bg-blue-600 text-white text-2xl font-bold rounded-xl hover:bg-blue-700 transition">Ingresar</button>
        </form>

    </div>

    <style>
        .logo {
            width: 150px;
            height: 150px;
        }
        form{
            padding: 10px 40px 10px 40px;
        }
    </style>
</body>
</html>
