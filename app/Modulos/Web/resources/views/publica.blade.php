{{-- Tienda pública: cascarón de la app Vue (resources/js/tienda). La app pide todo en UNA llamada a la API. Variables: $tienda --}}
@php
    $nombre = $tienda->nombre();
    $descripcion = $tienda->lema ?: \Illuminate\Support\Str::limit(strip_tags((string) $tienda->descripcion), 155) ?: "Productos de {$nombre}";
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $nombre }}</title>
    <meta name="description" content="{{ $descripcion }}">
    <meta property="og:title" content="{{ $nombre }}">
    <meta property="og:description" content="{{ $descripcion }}">
    <meta property="og:type" content="website">
    <meta name="theme-color" content="{{ $tienda->color }}">
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700" rel="stylesheet">
    @vite(['resources/css/tienda.css', 'resources/js/tienda/main.js'])
</head>
<body class="bg-slate-50 font-sans text-slate-900 antialiased" style="--marca: {{ $tienda->color }}">
    <div id="tienda" data-api="{{ route('api.tienda.datos', $tienda->slug) }}" data-pedidos="{{ route('api.tienda.pedidos.store', $tienda->slug) }}" data-slug="{{ $tienda->slug }}">
        {{-- Mientras carga la app --}}
        <div class="grid min-h-dvh place-items-center text-slate-500">
            <p class="text-lg font-semibold">{{ $nombre }}</p>
        </div>
    </div>
    <noscript><p style="padding:24px;text-align:center">Activa JavaScript para ver la tienda.</p></noscript>
</body>
</html>
