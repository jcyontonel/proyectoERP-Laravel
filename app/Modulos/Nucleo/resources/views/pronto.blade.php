{{-- Variable: $item (config/menu.php): clave, label, icon, descripcion, seccion --}}
@extends('layouts.app')

@section('title', $item['label'])

@section('content')
    <x-page-header :title="$item['label']" :migas="array_filter([$item['seccion'] => null, $item['label'] => null], fn ($v, $k) => $k, ARRAY_FILTER_USE_BOTH)" />

    <section class="mt-6 rounded-xl border border-dashed border-slate-300 bg-white px-6 py-14 text-center shadow-sm">
        <span class="mx-auto grid size-14 place-items-center rounded-2xl bg-brand-50 text-brand-600">
            <x-icon :name="$item['icon']" class="size-7" />
        </span>
        <x-badge tone="info" class="mt-5"><x-icon name="sparkles" class="size-3" /> Próximamente</x-badge>
        <h2 class="mt-3 text-lg font-semibold">{{ $item['label'] }} está en construcción</h2>
        <p class="mx-auto mt-2 max-w-md text-sm leading-relaxed text-slate-500">
            {{ $item['descripcion'] ?? 'Este módulo forma parte del plan del sistema y estará disponible en una próxima versión.' }}
        </p>
        <div class="mt-6 flex flex-wrap justify-center gap-2">
            <a href="{{ url()->previous() !== url()->current() ? url()->previous() : route('inicio') }}" class="btn btn-secondary"><x-icon name="chevron-left" /> Volver</a>
            <a href="{{ route('inicio') }}" class="btn btn-primary"><x-icon name="home" /> Ir al inicio</a>
        </div>
    </section>
@endsection
