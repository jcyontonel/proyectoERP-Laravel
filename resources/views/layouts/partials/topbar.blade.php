{{-- Barra superior: menú móvil, buscador, venta rápida, caja, alertas, configuración y usuario --}}
@php
    $config = $menu->firstWhere('config', true);
    $cuenta = $menu->firstWhere('oculta', true);
    $tonosAlerta = [
        'danger'  => 'bg-red-50 text-red-600',
        'warning' => 'bg-amber-50 text-amber-600',
        'info'    => 'bg-brand-50 text-brand-600',
        'neutral' => 'bg-slate-100 text-slate-500',
    ];
    $urgentes = $alertas->whereIn('tono', ['danger', 'warning'])->count();
@endphp

<header class="sticky top-0 z-30 border-b border-slate-200 bg-white/95 backdrop-blur">
    <div class="flex h-16 items-center gap-2 px-4 sm:gap-3 sm:px-6 lg:px-8">
        <button type="button" data-sidebar-abrir aria-label="Abrir menú" aria-controls="sidebar" class="btn-icon -ml-1.5 lg:hidden">
            <x-icon name="menu" class="size-5" />
        </button>

        {{-- Buscador global (por ahora busca en el catálogo de productos) --}}
        <form method="GET" action="{{ route('productos.index') }}" role="search" class="relative hidden max-w-md flex-1 sm:block">
            <label for="buscador-global" class="sr-only">Buscar</label>
            <x-icon name="search" class="pointer-events-none absolute left-3 top-3 text-slate-400" />
            <input id="buscador-global" name="q" type="search" data-buscador autocomplete="off"
                placeholder="Buscar producto por nombre o código…" class="field bg-slate-50 pl-9 pr-14">
            <kbd class="pointer-events-none absolute right-2.5 top-2.5 hidden rounded border border-slate-200 bg-white px-1.5 text-[10px] font-medium leading-5 text-slate-400 md:block">Ctrl K</kbd>
        </form>

        <div class="ml-auto flex items-center gap-1 sm:gap-2">
            @can('emitir-ventas')
                <a href="{{ route('ventas.create') }}" class="btn btn-primary h-9 px-3"><x-icon name="plus" /><span class="hidden md:inline">Nueva venta</span></a>
            @endcan

            {{-- Estado de caja --}}
            @can('gestionar-caja')
                <a href="{{ route('caja.index') }}" title="{{ $cajaAbierta ? 'Turno abierto desde las ' . $cajaAbierta->fecha_apertura->format('H:i') : 'No tienes un turno de caja abierto' }}"
                    class="hidden items-center gap-2 rounded-lg border border-slate-200 px-3 py-1.5 text-xs font-medium text-slate-600 hover:bg-slate-50 md:flex">
                    <span @class(['size-2 rounded-full', 'bg-emerald-500' => $cajaAbierta, 'bg-slate-300' => ! $cajaAbierta])></span>
                    {{ $cajaAbierta ? ($cajaAbierta->caja?->nombre ?? 'Caja') . ' abierta' : 'Caja cerrada' }}
                </a>
            @endcan

            {{-- Alertas --}}
            <details data-dropdown class="relative">
                <summary class="btn-icon relative cursor-pointer list-none [&::-webkit-details-marker]:hidden" aria-label="Alertas ({{ $alertas->count() }})">
                    <x-icon name="bell" class="size-[18px]" />
                    @if ($alertas->isNotEmpty())
                        <span @class([
                            'absolute right-1 top-1 grid min-w-4 place-items-center rounded-full px-1 text-[10px] font-bold leading-4 text-white',
                            'bg-red-500' => $urgentes > 0,
                            'bg-slate-400' => $urgentes === 0,
                        ])>{{ $alertas->count() }}</span>
                    @endif
                </summary>

                <div class="menu-panel w-[min(22rem,calc(100vw-2rem))] p-0">
                    <div class="flex items-center justify-between border-b border-slate-100 px-4 py-3">
                        <p class="text-sm font-semibold">Alertas</p>
                        <span class="text-[11px] text-slate-500">{{ $empresaActual?->nombre_comercial }}</span>
                    </div>

                    @forelse ($alertas as $a)
                        <a href="{{ $a->url ?? '#' }}" class="flex items-start gap-3 border-b border-slate-100 px-4 py-3 last:border-0 hover:bg-slate-50">
                            <span class="grid size-8 shrink-0 place-items-center rounded-lg {{ $tonosAlerta[$a->tono] ?? $tonosAlerta['neutral'] }}"><x-icon :name="$a->icono" /></span>
                            <span class="min-w-0 leading-tight">
                                <span class="block text-sm font-medium text-ink">{{ $a->titulo }}</span>
                                <span class="mt-0.5 block text-xs text-slate-500">{{ $a->detalle }}</span>
                            </span>
                        </a>
                    @empty
                        <div class="px-4 py-8 text-center">
                            <x-icon name="check-circle" class="mx-auto size-6 text-emerald-500" />
                            <p class="mt-2 text-sm font-medium">Todo en orden</p>
                            <p class="text-xs text-slate-500">No hay alertas pendientes.</p>
                        </div>
                    @endforelse
                </div>
            </details>

            {{-- Configuración --}}
            @if ($config && $config->items->isNotEmpty())
                <details data-dropdown class="relative hidden sm:block">
                    <summary class="btn-icon cursor-pointer list-none [&::-webkit-details-marker]:hidden" aria-label="Configuración">
                        <x-icon name="settings" class="size-[18px]" />
                    </summary>
                    <div class="menu-panel w-64">
                        <p class="px-3 pb-1 pt-1.5 text-[11px] font-medium uppercase tracking-wide text-slate-400">Configuración</p>
                        @foreach ($config->items as $item)
                            <a href="{{ $item->url }}" class="menu-item">
                                <x-icon :name="$item->icon" class="text-slate-400" />
                                <span class="flex-1">{{ $item->label }}</span>
                                @if ($item->pronto)<span class="text-[10px] text-slate-400">Pronto</span>@endif
                            </a>
                        @endforeach
                    </div>
                </details>
            @endif

            {{-- Usuario --}}
            <details data-dropdown class="relative">
                <summary class="flex cursor-pointer list-none items-center gap-2.5 rounded-lg p-1 hover:bg-slate-100 [&::-webkit-details-marker]:hidden">
                    <span class="grid size-9 place-items-center rounded-full bg-brand-100 text-xs font-semibold text-brand-700">{{ $usuario->iniciales }}</span>
                    <span class="hidden text-left leading-tight xl:block">
                        <span class="block text-sm font-semibold">{{ $usuario->nombre_completo }}</span>
                        <span class="block text-xs text-slate-500">{{ $rolActual }}</span>
                    </span>
                    <x-icon name="chevron-down" class="hidden text-slate-400 xl:block" />
                </summary>

                <div class="menu-panel w-64">
                    <div class="px-3 py-2">
                        <p class="truncate text-sm font-semibold">{{ $usuario->nombre_completo }}</p>
                        <p class="truncate text-xs text-slate-500">{{ $usuario->email }}</p>
                        <x-badge tone="info" class="mt-2">{{ $rolActual }}</x-badge>
                    </div>

                    <div class="border-t border-slate-100 pt-1.5">
                        @foreach ($cuenta?->items ?? [] as $item)
                            <a href="{{ $item->url }}" class="menu-item">
                                <x-icon :name="$item->icon" class="text-slate-400" /> {{ $item->label }}
                            </a>
                        @endforeach
                    </div>

                    <form method="POST" action="{{ route('logout') }}" class="mt-1.5 border-t border-slate-100 pt-1.5">
                        @csrf
                        <button type="submit" class="menu-item text-red-600 hover:bg-red-50">
                            <x-icon name="log-out" /> Cerrar sesión
                        </button>
                    </form>
                </div>
            </details>
        </div>
    </div>
</header>
