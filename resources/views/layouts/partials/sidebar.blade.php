{{-- Barra lateral: empresa, menú por secciones (config/menu.php) y ayuda. Móvil: cajón con fondo oscuro --}}
<div data-sidebar-fondo hidden class="fixed inset-0 z-40 bg-slate-900/40 lg:hidden"></div>

<aside id="sidebar" data-sidebar aria-label="Menú principal"
    class="fixed inset-y-0 left-0 z-50 flex w-72 -translate-x-full flex-col border-r border-slate-200 bg-white transition-[transform,width] duration-200 data-[abierto]:translate-x-0 lg:w-64 lg:translate-x-0 lg:mini:w-[4.5rem]">

    {{-- Marca --}}
    <div class="flex h-16 shrink-0 items-center gap-2.5 border-b border-slate-200 px-4 lg:mini:justify-center lg:mini:px-0">
        <a href="{{ route('inicio') }}" class="flex min-w-0 items-center gap-2.5">
            <img src="{{ asset('images/logo.png') }}" alt="" class="h-9 w-auto shrink-0">
            <span class="truncate text-lg font-bold tracking-tight lg:mini:hidden">{{ str($empresaActual?->nombre_comercial ?? config('app.name'))->upper() }}</span>
        </a>
        <button type="button" data-sidebar-cerrar aria-label="Cerrar menú" class="btn-icon ml-auto lg:hidden"><x-icon name="x" class="size-5" /></button>
    </div>

    {{-- Empresa actual (selector si tiene varias) --}}
    @if ($empresaActual)
        <div class="shrink-0 border-b border-slate-200 p-3 lg:mini:px-2">
            <details data-dropdown class="relative">
                <summary @class([
                    'flex list-none items-center gap-2.5 rounded-lg border border-slate-200 px-2.5 py-2 [&::-webkit-details-marker]:hidden lg:mini:justify-center lg:mini:border-0 lg:mini:p-1.5',
                    'cursor-pointer hover:bg-slate-50' => $empresas->count() > 1,
                ]) title="{{ $empresaActual->razon_social }}">
                    @if ($empresaActual->logo_path)
                        <img src="{{ route('config.empresa.logo') }}?v={{ $empresaActual->updated_at?->timestamp }}" alt="" class="size-8 shrink-0 rounded-md object-contain">
                    @else
                        <span class="grid size-8 shrink-0 place-items-center rounded-md bg-brand-50 text-brand-700"><x-icon name="store" /></span>
                    @endif
                    <span class="min-w-0 flex-1 leading-tight lg:mini:hidden">
                        <span class="block truncate text-sm font-semibold">{{ $empresaActual->nombre_comercial ?: $empresaActual->razon_social }}</span>
                        <span class="block truncate text-[11px] text-slate-500">RUC {{ $empresaActual->ruc }}</span>
                    </span>
                    @if ($empresas->count() > 1)
                        <x-icon name="chevrons-up-down" class="text-slate-400 lg:mini:hidden" />
                    @endif
                </summary>

                @if ($empresas->count() > 1)
                    <div class="menu-panel left-0 right-auto w-64">
                        <p class="px-3 pb-1 pt-1.5 text-[11px] font-medium uppercase tracking-wide text-slate-400">Cambiar de empresa</p>
                        @foreach ($empresas as $e)
                            <form method="POST" action="{{ route('empresa.cambiar') }}">
                                @csrf
                                <input type="hidden" name="empresa_id" value="{{ $e->id }}">
                                <button type="submit" @class(['menu-item', 'bg-brand-50 text-brand-700' => $e->id === $empresaActual->id]) @disabled($e->id === $empresaActual->id)>
                                    <x-icon name="store" class="text-slate-400" />
                                    <span class="min-w-0 flex-1 leading-tight">
                                        <span class="block truncate font-medium">{{ $e->nombre_comercial ?: $e->razon_social }}</span>
                                        <span class="block text-[11px] text-slate-500">RUC {{ $e->ruc }}</span>
                                    </span>
                                    @if ($e->id === $empresaActual->id)<x-icon name="check" class="text-brand-600" />@endif
                                </button>
                            </form>
                        @endforeach
                    </div>
                @endif
            </details>
        </div>
    @endif

    {{-- Menú --}}
    <nav class="flex-1 space-y-1 overflow-y-auto px-3 py-3 lg:mini:px-2" aria-label="Principal">
        @foreach ($menu->where('oculta', false) as $seccion)
            @if ($seccion->titulo)
                <details data-seccion="{{ str($seccion->titulo)->slug() }}" class="group" @if ($seccion->abierta || ! $loop->last) open @endif>
                    <summary class="mt-2 flex cursor-pointer list-none items-center justify-between rounded-md px-2 py-1.5 text-[11px] font-semibold uppercase tracking-wide text-slate-400 hover:text-slate-600 [&::-webkit-details-marker]:hidden lg:mini:hidden">
                        {{ $seccion->titulo }}
                        <x-icon name="chevron-down" class="size-3.5 transition group-open:rotate-180" />
                    </summary>
                    <div class="hidden border-t border-slate-100 lg:mini:my-2 lg:mini:block"></div>
                    <ul class="mt-0.5 space-y-0.5">
                        @foreach ($seccion->items as $item)
                            @include('layouts.partials.sidebar-item')
                        @endforeach
                    </ul>
                </details>
            @else
                <ul class="space-y-0.5">
                    @foreach ($seccion->items as $item)
                        @include('layouts.partials.sidebar-item')
                    @endforeach
                </ul>
            @endif
        @endforeach
    </nav>

    {{-- Pie: ayuda y colapsar --}}
    <div class="shrink-0 space-y-0.5 border-t border-slate-200 p-3 lg:mini:px-2">
        <a href="{{ route('pronto', 'ayuda') }}" title="Centro de ayuda" class="flex items-center gap-3 rounded-lg px-2.5 py-2 text-sm text-slate-600 hover:bg-slate-100 hover:text-ink lg:mini:justify-center">
            <x-icon name="help-circle" class="size-[18px] text-slate-400" />
            <span class="lg:mini:hidden">Ayuda y soporte</span>
        </a>
        <button type="button" data-sidebar-mini class="hidden w-full items-center gap-3 rounded-lg px-2.5 py-2 text-sm text-slate-600 hover:bg-slate-100 hover:text-ink lg:flex lg:mini:justify-center"
            aria-label="Contraer o expandir el menú">
            <x-icon name="panel-left" class="size-[18px] text-slate-400" />
            <span class="lg:mini:hidden">Contraer menú</span>
        </button>
    </div>
</aside>
