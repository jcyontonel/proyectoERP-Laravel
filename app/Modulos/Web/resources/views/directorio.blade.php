{{-- Directorio de tiendas publicadas (PublicaController@directorio): $tiendas --}}
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Tiendas · {{ config('app.name') }}</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700" rel="stylesheet">
    @vite(['resources/css/tienda.css'])
</head>
<body class="min-h-dvh bg-slate-50 font-sans text-slate-900 antialiased">
    <main class="mx-auto max-w-4xl px-4 py-10">
        <h1 class="text-2xl font-bold">Tiendas</h1>
        <p class="mt-1 text-sm text-slate-500">Empresas con su página web publicada.</p>

        @if ($tiendas->isEmpty())
            <p class="mt-8 rounded-2xl bg-white p-8 text-center text-sm text-slate-500 ring-1 ring-slate-200">
                Aún no hay tiendas publicadas. Una empresa con plan web la publica desde el ERP: Página web → Mi tienda.
            </p>
        @else
            <ul class="mt-6 grid gap-4 sm:grid-cols-2">
                @foreach ($tiendas as $t)
                    <li>
                        <a href="{{ route('tienda.publica', $t->slug) }}" class="flex items-center gap-4 rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200 transition hover:shadow-md">
                            <span class="grid size-12 shrink-0 place-items-center rounded-xl text-lg font-bold text-white" style="background: {{ $t->color }}">{{ mb_substr($t->nombre(), 0, 1) }}</span>
                            <span class="min-w-0">
                                <span class="block truncate font-semibold">{{ $t->nombre() }}</span>
                                @if ($t->lema)<span class="block truncate text-sm text-slate-500">{{ $t->lema }}</span>@endif
                                <span class="block truncate text-xs text-slate-400">{{ route('tienda.publica', $t->slug) }}</span>
                            </span>
                        </a>
                    </li>
                @endforeach
            </ul>
        @endif
    </main>
</body>
</html>
