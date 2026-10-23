@props(['metodo' => null, 'label' => null])

@php
    $icons = [
        'tarjeta'       => 'credit-card',
        'yape'          => 'smartphone',
        'plin'          => 'smartphone',
        'efectivo'      => 'banknote',
        'transferencia' => 'banknote',
        'mixto'         => 'wallet',
    ];
@endphp

<span {{ $attributes->merge(['class' => 'inline-flex items-center gap-1.5 whitespace-nowrap text-xs text-slate-600']) }}>
    @if ($metodo)
        <x-icon :name="$icons[$metodo] ?? 'credit-card'" class="size-3.5 text-slate-400" />
        {{ $label }}
    @else
        <span class="text-slate-400">—</span> Por definir
    @endif
</span>