@props(['tone' => 'success'])

@php
    $tones = [
        'success' => 'bg-emerald-50 text-emerald-700',
        'danger'  => 'bg-red-50 text-red-700',
        'warning' => 'bg-amber-50 text-amber-800',
        'info'    => 'bg-brand-50 text-brand-700',
    ];
    $icons = ['success' => 'check-circle', 'danger' => 'alert-triangle', 'warning' => 'alert-triangle', 'info' => 'info'];
@endphp

<div role="{{ $tone === 'danger' ? 'alert' : 'status' }}" {{ $attributes->class(['flex items-start gap-2 rounded-lg px-4 py-2.5 text-sm', $tones[$tone] ?? $tones['info']]) }}>
    <x-plataforma::icon :name="$icons[$tone] ?? 'info'" class="mt-0.5" />
    <div class="min-w-0">{{ $slot }}</div>
</div>
