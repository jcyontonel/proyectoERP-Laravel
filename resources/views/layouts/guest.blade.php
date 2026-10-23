<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Iniciar sesión') · {{ config('app.name', 'Ricopet') }}</title>

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700" rel="stylesheet">

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @stack('head')
</head>

<body class="min-h-dvh bg-canvas font-sans text-ink antialiased">
    <main class="mx-auto flex min-h-dvh w-full max-w-sm flex-col justify-start px-4 pb-10 pt-16 sm:justify-center sm:pt-10">
        <a href="{{ url('/') }}" class="mx-auto flex flex-col items-center" aria-label="{{ config('app.name', 'Ricopet') }}, gestión comercial">
            <span class="flex items-center gap-3">
                <span class="grid size-11 place-items-center rounded-xl bg-accent-500 text-white">
                    <x-icon name="paw" class="size-6" />
                </span>
                <span class="text-4xl font-bold tracking-tight">{{ config('app.name', 'Ricopet') }}</span>
            </span>
            <span class="mt-2 text-xs text-slate-500">Gestión comercial</span>
        </a>

        <div class="mt-7">
            @yield('content')
        </div>
    </main>

    @stack('scripts')
</body>
</html>
