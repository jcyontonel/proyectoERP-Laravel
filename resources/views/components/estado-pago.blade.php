@props(['estado' => null])

@php
    $map = [
        'pagada'    => ['success', 'Pagada'],
        'parcial'   => ['info', 'Pago parcial'],
        'pendiente' => ['warning', 'Por pagar'],
    ];
@endphp

@if ($estado && isset($map[$estado]))
    <x-badge :tone="$map[$estado][0]" shape="box" {{ $attributes }}>{{ $map[$estado][1] }}</x-badge>
@else
    <span class="text-xs text-slate-400">—</span>
@endif
