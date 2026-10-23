@props(['title', 'icon', 'value', 'tone' => 'brand', 'href' => null])

@php
    $tonos = [
        'brand'   => 'bg-brand-50 text-brand-600',
        'amber'   => 'bg-amber-50 text-amber-600',
        'red'     => 'bg-red-50 text-red-600',
        'emerald' => 'bg-emerald-50 text-emerald-600',
        'slate'   => 'bg-slate-100 text-slate-500',
    ];
@endphp

<div {{ $attributes->class(['relative rounded-xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5', 'transition hover:border-brand-200 hover:shadow' => $href]) }}>
    <div class="flex items-start justify-between gap-3">
        <p class="text-sm text-slate-500">
            @if ($href)
                <a href="{{ $href }}" class="after:absolute after:inset-0">{{ $title }}</a>
            @else
                {{ $title }}
            @endif
        </p>
        <span class="grid size-8 shrink-0 place-items-center rounded-lg {{ $tonos[$tone] ?? $tonos['brand'] }}">
            <x-plataforma::icon :name="$icon" class="size-[18px]" />
        </span>
    </div>
    <p class="mt-3 text-2xl font-bold tracking-tight sm:text-3xl">{{ $value }}</p>
    @if ($slot->isNotEmpty())
        <div class="mt-3 flex flex-wrap items-center gap-x-2 gap-y-1 text-xs text-slate-500">
            {{ $slot }}
        </div>
    @endif
</div>
