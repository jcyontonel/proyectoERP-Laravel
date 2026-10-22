@props(['tone' => 'neutral', 'shape' => 'pill', 'dot' => false])

@php
    $tones = [
        'success' => 'bg-emerald-50 text-emerald-700',
        'warning' => 'bg-amber-50 text-amber-700',
        'danger'  => 'bg-red-50 text-red-600',
        'neutral' => 'bg-slate-100 text-slate-600',
        'info'    => 'bg-brand-50 text-brand-700',
    ];
    $dots = [
        'success' => 'bg-emerald-500',
        'warning' => 'bg-amber-500',
        'danger'  => 'bg-red-500',
        'neutral' => 'bg-slate-400',
        'info'    => 'bg-brand-500',
    ];
@endphp

<span {{ $attributes->class([
    'inline-flex items-center gap-1.5 whitespace-nowrap px-2.5 py-0.5 text-[11px] font-medium',
    $tones[$tone] ?? $tones['neutral'],
    'rounded-full' => $shape === 'pill',
    'rounded-md' => $shape === 'box',
]) }}>
    @if ($dot)<span class="size-1.5 rounded-full {{ $dots[$tone] ?? $dots['neutral'] }}"></span>@endif{{ $slot }}
</span>