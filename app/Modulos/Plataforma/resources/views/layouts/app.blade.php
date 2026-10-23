{{-- Layout de Plataforma (administración global). Secciones: title, content · Pilas: scripts --}}
@php
    $menu = [
        ['plataforma.inicio', 'Panel', 'home', ['plataforma.inicio']],
        ['plataforma.empresas.index', 'Empresas', 'building', ['plataforma.empresas.*']],
        ['plataforma.pagos.index', 'Pagos', 'receipt', ['plataforma.pagos.*']],
        ['plataforma.planes.index', 'Planes', 'layers', ['plataforma.planes.*']],
        ['plataforma.ofertas.index', 'Ofertas', 'tag', ['plataforma.ofertas.*']],
    ];
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Panel') · {{ config('app.name') }}</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700" rel="stylesheet">
    @vite(['resources/css/app.css'])
</head>
<body class="min-h-dvh bg-canvas font-sans text-ink antialiased">
    <a href="#contenido" class="sr-only focus:not-sr-only focus:fixed focus:left-4 focus:top-4 focus:z-50 focus:rounded-lg focus:bg-white focus:px-4 focus:py-2 focus:shadow">Saltar al contenido</a>

    <div class="flex min-h-dvh flex-col lg:flex-row">
        <aside class="border-b border-slate-200 bg-white lg:fixed lg:inset-y-0 lg:w-60 lg:border-b-0 lg:border-r" aria-label="Menú principal">
            <div class="flex h-16 items-center gap-2.5 px-5">
                <span class="grid size-9 place-items-center rounded-lg bg-brand-600 text-white"><x-plataforma::icon name="shield" class="size-5" /></span>
                <span class="leading-tight">
                    <span class="block text-sm font-bold">RicoPet</span>
                    <span class="block text-[11px] text-slate-500">Plataforma</span>
                </span>
            </div>
            <nav class="flex gap-1 overflow-x-auto px-3 pb-3 lg:flex-col lg:pb-0">
                @foreach ($menu as [$ruta, $label, $icono, $activo])
                    @php $actual = request()->routeIs(...$activo); @endphp
                    <a href="{{ route($ruta) }}" @if ($actual) aria-current="page" @endif
                        @class(['flex shrink-0 items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium',
                            'bg-brand-50 text-brand-700' => $actual, 'text-slate-600 hover:bg-slate-100 hover:text-ink' => ! $actual])>
                        <x-plataforma::icon :name="$icono" class="size-[18px]" /> {{ $label }}
                    </a>
                @endforeach
            </nav>
            <div class="hidden px-3 lg:absolute lg:inset-x-0 lg:bottom-0 lg:block lg:border-t lg:border-slate-200 lg:p-3">
                <a href="{{ route('inicio') }}" class="flex items-center gap-3 rounded-lg px-3 py-2 text-sm text-slate-600 hover:bg-slate-100">
                    <x-plataforma::icon name="external-link" class="size-[18px]" /> Abrir el ERP
                </a>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="flex w-full items-center gap-3 rounded-lg px-3 py-2 text-left text-sm text-slate-600 hover:bg-slate-100">
                        <x-plataforma::icon name="log-out" class="size-[18px]" /> Salir <span class="ml-auto truncate text-xs text-slate-400">{{ auth()->user()->name }}</span>
                    </button>
                </form>
            </div>
        </aside>

        <main id="contenido" class="w-full flex-1 px-4 pb-10 pt-6 sm:px-6 lg:ml-60 lg:px-8">
            <div class="mx-auto max-w-7xl">
                @yield('content')
            </div>
        </main>
    </div>

    <script>
        // Formularios con acciones delicadas: <form data-confirmar="¿Seguro…?">
        document.addEventListener('submit', (e) => {
            const mensaje = e.target.dataset?.confirmar;
            if (mensaje && !window.confirm(mensaje)) e.preventDefault();
        });
        // Diálogos: <button data-abrir="id-del-dialog"> … <button data-cerrar>
        document.addEventListener('click', (e) => {
            const abrir = e.target.closest('[data-abrir]');
            if (abrir) {
                document.getElementById(abrir.dataset.abrir)?.showModal();
                return;
            }
            const dialogo = e.target.closest('dialog');
            if (e.target.closest('[data-cerrar]') || e.target === dialogo) dialogo?.close();
        });
        // Reabre el diálogo cuyo formulario volvió con errores
        document.querySelectorAll('dialog[data-abrir-al-cargar]').forEach((d) => d.showModal());
    </script>
    @stack('scripts')
</body>
</html>
