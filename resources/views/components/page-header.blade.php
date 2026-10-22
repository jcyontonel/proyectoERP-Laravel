{{--
    Encabezado de página: migas, título, subtítulo y acciones.
    <x-page-header title="Productos" subtitle="..." :migas="['Inventario' => null, 'Productos' => null]">
        <x-slot:acciones>...</x-slot:acciones>
    </x-page-header>
    migas: [etiqueta => url|null]; "Inicio" se agrega solo.
--}}
@props(['title', 'subtitle' => null, 'migas' => []])

<div {{ $attributes }}>
    <nav aria-label="Ruta" class="flex flex-wrap items-center gap-2 text-xs text-slate-500">
        <a href="{{ route('inicio') }}" class="hover:text-brand-600">Inicio</a>
        @foreach ($migas as $etiqueta => $url)
            <x-icon name="chevron-right" class="size-3" />
            @if ($url && ! $loop->last)
                <a href="{{ $url }}" class="hover:text-brand-600">{{ $etiqueta }}</a>
            @else
                <span @class(['text-ink' => $loop->last])>{{ $etiqueta }}</span>
            @endif
        @endforeach
    </nav>

    <div class="mt-4 flex flex-wrap items-start justify-between gap-x-6 gap-y-3">
        <div class="min-w-0">
            <div class="flex flex-wrap items-center gap-3">
                <h1 class="text-2xl font-bold tracking-tight sm:text-3xl">{{ $title }}</h1>
                {{ $badge ?? '' }}
            </div>
            @if ($subtitle)
                <p class="mt-1.5 max-w-2xl text-sm text-slate-500">{{ $subtitle }}</p>
            @endif
        </div>
        @isset($acciones)
            <div class="flex flex-wrap items-center gap-2">{{ $acciones }}</div>
        @endisset
    </div>
</div>
