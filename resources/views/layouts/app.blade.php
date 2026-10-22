{{--
    Layout principal. LayoutComposer (ErpServiceProvider) entrega:
      $usuario, $rolActual, $empresaActual, $empresas, $menu (config/menu.php), $alertas, $cajaAbierta
    Secciones: title, content, shortcuts (pie de página) · Pilas: head, scripts
--}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Inicio') · {{ $empresaActual?->nombre_comercial ?? config('app.name', 'Ricopet') }}</title>

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700" rel="stylesheet">

    {{-- Aplica la barra lateral colapsada antes de pintar (evita el parpadeo) --}}
    <script>
        try { if (localStorage.getItem('sidebar') === 'mini') document.documentElement.classList.add('sidebar-mini'); } catch (e) {}
    </script>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @stack('head')
</head>

<body class="min-h-dvh bg-canvas font-sans text-ink antialiased">
    <a href="#contenido" class="sr-only focus:not-sr-only focus:fixed focus:left-4 focus:top-4 focus:z-50 focus:rounded-lg focus:bg-white focus:px-4 focus:py-2 focus:shadow">Saltar al contenido</a>

    @include('layouts.partials.sidebar')

    <div class="flex min-h-dvh flex-col transition-[padding] lg:pl-64 lg:mini:pl-[4.5rem]">
        @include('layouts.partials.topbar')

        <main id="contenido" class="mx-auto w-full max-w-7xl flex-1 px-4 pb-10 pt-5 sm:px-6 lg:px-8">
            @if (! $empresaActual)
                <x-alert tone="warning" class="mb-5">Tu usuario no tiene una empresa asignada. Pide a un administrador que te dé acceso.</x-alert>
            @endif

            @yield('content')
        </main>

        <footer class="mx-auto flex w-full max-w-7xl flex-wrap items-center justify-between gap-x-6 gap-y-1 px-4 pb-6 text-xs text-slate-500 sm:px-6 lg:px-8">
            <span>
                {{ $empresaActual?->razon_social ?? config('app.name') }}
                @if ($empresaActual) · RUC {{ $empresaActual->ruc }} @endif
            </span>
            <span>@yield('shortcuts', 'Importes en ' . ($empresaActual?->moneda ?? 'PEN') . ($empresaActual?->precios_incluyen_igv ?? true ? ' · Precios incluyen IGV' : ' · Precios sin IGV'))</span>
        </footer>
    </div>

    @stack('scripts')
</body>
</html>
